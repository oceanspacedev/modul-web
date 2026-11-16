(() => {
    const root = document.querySelector('[data-meeting-monitor]');
    if (!root) return;

    const joinForm = root.querySelector('[data-meeting-join]');
    if (!joinForm) return;

    const joinButton = joinForm.querySelector('[type="submit"]');
    const passcode = joinForm.querySelector('[data-meeting-passcode]');
    const leaveButton = root.querySelector('[data-meeting-leave]');
    const status = root.querySelector('[data-meeting-status]');
    const zoomAppRoot = root.querySelector('[data-meeting-client]');
    const liveCount = root.querySelector('[data-meeting-live-count]');
    const heartbeatInterval = 12000;
    const maxBatchSize = 100;
    let currentRun = null;

    function setStatus(message, state) {
        if (status.textContent !== message) status.textContent = message;
        status.dataset.state = state;
    }

    function updateLiveCount(window) {
        if (liveCount) liveCount.textContent = window ? String(window.participants.size) : '–';
    }

    async function post(url, data = {}) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': root.dataset.csrfToken,
            },
            body: JSON.stringify(data),
            signal: AbortSignal.timeout(8000),
        });

        const body = response.status === 204 ? null : await response.json().catch(() => null);
        if (!response.ok || response.redirected) {
            throw Object.assign(new Error(body?.message ?? 'Data monitoring belum dapat dikirim.'), {
                status: response.redirected ? 401 : response.status,
                code: body?.code,
            });
        }

        return body;
    }

    function readParticipants(run) {
        try {
            const currentUser = run.client.getCurrentUser();
            if (!currentUser || currentUser.isHold || currentUser.bHold) return null;

            const attendees = run.client.getAttendeeslist();
            if (!Array.isArray(attendees)) return null;

            const participants = new Map();
            for (const attendee of attendees) {
                // The operator and waiting-room entries are outside the monitored roster.
                if (attendee.userId === currentUser.userId || attendee.isHold || attendee.bHold) continue;
                if (!Number.isSafeInteger(attendee.userId)) continue;

                const name = String(attendee.userName ?? attendee.displayName ?? '').trim().slice(0, 255);
                const video = attendee.video ?? attendee.bVideoOn;
                participants.set(attendee.userId, {
                    key: String(attendee.userId),
                    name: name || 'Peserta Zoom',
                    camera: typeof video === 'boolean' ? video : null,
                });
            }

            return participants;
        } catch {
            return null;
        }
    }

    function record(window, type, participant, timestamp) {
        window.events.push({
            id: crypto.randomUUID(),
            type,
            observed_at: timestamp,
            participant_key: participant.key,
            name: participant.name,
            camera_on: type === 'leave' ? null : participant.camera,
        });
    }

    function startWindow(run, participants = readParticipants(run)) {
        if (!participants || run.window || !run.connected || !run.joined || run.stopped) return;

        const startedAt = new Date().toISOString();
        const window = {
            id: crypto.randomUUID(),
            startedAt,
            endedAt: null,
            opened: false,
            lastSync: 0,
            participants,
            missing: new Set(),
            events: [],
            chats: [],
        };
        run.window = window;
        run.windows.push(window);
        for (const participant of participants.values()) {
            record(window, 'present', participant, startedAt);
        }
        updateLiveCount(window);
    }

    function closeWindow(run) {
        if (!run.window) return;

        run.window.endedAt = new Date().toISOString();
        run.window = null;
        updateLiveCount(null);
    }

    function capture(run, eventType, payload) {
        if (!run.connected || !run.joined || run.stopped) return;

        const latest = readParticipants(run);
        if (!latest) return;
        if (!run.window) {
            startWindow(run, latest);
            return;
        }

        // The roster supplies the current state. The event identifies the affected users.
        const window = run.window;
        const timestamp = new Date().toISOString();
        const changes = Array.isArray(payload) ? payload : [payload];

        for (const change of changes) {
            const id = change?.userId;
            const before = window.participants.get(id);
            const after = latest.get(id);

            if (before && !after && (eventType === 'leave' || change?.isHold || change?.bHold)) {
                record(window, 'leave', before, timestamp);
                window.participants.delete(id);
            } else if (!before && after) {
                record(window, eventType === 'join' ? 'join' : 'present', after, timestamp);
                window.participants.set(id, after);
            } else if (before && after) {
                if (after.camera !== null && after.camera !== before.camera) {
                    record(window, before.camera === null ? 'present' : 'camera', after, timestamp);
                }
                window.participants.set(id, { ...after, camera: after.camera ?? before.camera });
            }
        }

        updateLiveCount(window);
    }

    // Keep chat sent to everyone. A message addressed to a user (receiver ID above 0) is private, and the
    // observer's own messages are not participant activity.
    function captureChat(run, payload) {
        if (!run.connected || !run.joined || run.stopped) return;
        if (!run.window) startWindow(run);
        const window = run.window;
        if (!window) return;

        try {
            const receiverId = payload?.receiver?.userId;
            if (Number.isSafeInteger(receiverId) && receiverId > 0) return;
            if (payload?.sender?.userId === run.client.getCurrentUser()?.userId) return;
        } catch {
            return;
        }

        const text = (Array.isArray(payload?.message) ? payload.message.join('\n') : String(payload?.message ?? '')).trim();
        if (!text) return;

        // Zoom sends milliseconds; accept seconds too in case a release changes the unit.
        let sentAt = Number(payload?.timestamp);
        if (Number.isFinite(sentAt) && sentAt > 0 && sentAt < 1e12) sentAt *= 1000;
        if (!Number.isFinite(sentAt) || sentAt <= 0 || sentAt > Date.now() + 60000) sentAt = Date.now();

        const id = typeof payload?.id === 'string' && payload.id && payload.id.length <= 128 ? payload.id : crypto.randomUUID();
        if (window.chats.some((chat) => chat.id === id)) return;

        window.chats.push({
            id,
            sent_at: new Date(sentAt).toISOString(),
            sender: String(payload?.sender?.name ?? '').trim().slice(0, 255) || 'Peserta Zoom',
            message: text.slice(0, 4000),
        });
    }

    // Zoom events can arrive before the roster changes, or not at all. Compare the whole roster on
    // every pump so a missed join, leave or camera change is still recorded.
    function reconcile(run) {
        const window = run.window;
        if (!window || !run.connected || !run.joined || run.stopped) return;

        const latest = readParticipants(run);
        if (!latest) return;

        const timestamp = new Date().toISOString();
        const missing = new Set();
        for (const [id, before] of window.participants) {
            if (latest.has(id)) continue;

            // Wait for a second pass so a roster that is briefly incomplete does not log a leave.
            if (window.missing.has(id)) {
                record(window, 'leave', before, timestamp);
                window.participants.delete(id);
            } else {
                missing.add(id);
            }
        }
        window.missing = missing;

        for (const [id, after] of latest) {
            const before = window.participants.get(id);
            if (!before) {
                record(window, 'present', after, timestamp);
                window.participants.set(id, after);
            } else if (after.camera !== null && after.camera !== before.camera) {
                record(window, before.camera === null ? 'present' : 'camera', after, timestamp);
                window.participants.set(id, after);
            }
        }

        updateLiveCount(window);
    }

    function monitoringStatus(run) {
        if (run.stopped) {
            setStatus('Menyimpan aktivitas Zoom terakhir…', 'waiting');
        } else if (!run.connected) {
            setStatus(run.wasConnected ? 'Koneksi Zoom terputus. Menunggu tersambung kembali…' : 'Menunggu masuk ke Zoom…', 'waiting');
        } else if (!run.window?.opened) {
            setStatus('Menyiapkan pencatatan aktivitas Zoom…', 'waiting');
        } else if (run.hasGap) {
            setStatus('Monitoring aktif. Ada bagian sesi yang belum terekam.', 'warning');
        } else {
            setStatus('Monitoring Zoom aktif.', 'active');
        }
    }

    function finish(run) {
        clearInterval(run.timer);
        try {
            for (const [event, handler] of Object.entries(run.listeners)) {
                run.client?.off(event, handler);
            }
            if (run.client) run.sdk.destroyClient();
        } catch {
            // The SDK may already have disposed its client after a failed join.
        } finally {
            zoomAppRoot.hidden = true;
            leaveButton.hidden = true;
            leaveButton.disabled = false;
            joinButton.disabled = false;
            currentRun = null;
        }
        updateLiveCount(null);
        setStatus(
            run.error ?? (run.hasGap
                ? 'Monitoring selesai. Sebagian aktivitas belum terekam.'
                : 'Monitoring selesai. Aktivitas tersimpan.'),
            run.error || run.hasGap ? 'warning' : 'idle',
        );
    }

    async function leave(run) {
        if (run.stopped) return;

        run.stopped = true;
        run.connected = false;
        leaveButton.disabled = true;
        closeWindow(run);
        try {
            await run.client.leaveMeeting();
        } catch {
            // Zoom may already have closed the connection.
        }
        void pump(run);
    }

    async function pump(run) {
        if (run.sending || currentRun !== run) return;
        run.sending = true;
        let stage = null;
        let batchSize = 0;
        let chatBatchSize = 0;

        try {
            if (run.connected && !run.window) startWindow(run);
            else reconcile(run);
            const window = run.windows[0];
            if (window) {
                stage = 'start';
                if (!window.opened) {
                    await post(root.dataset.startUrl, {
                        id: window.id,
                        observed_at: window.startedAt,
                        meeting_code: run.meetingNumber,
                        account_revision: run.accountRevision,
                    });
                    window.opened = true;
                    window.lastSync = Date.now();
                }

                if (window.events.length || window.chats.length || Date.now() - window.lastSync >= heartbeatInterval) {
                    const events = window.events.slice(0, maxBatchSize);
                    const chats = window.chats.slice(0, maxBatchSize);
                    stage = 'sync';
                    batchSize = events.length;
                    chatBatchSize = chats.length;
                    await post(root.dataset.syncUrl.replace('__MONITOR__', window.id), { events, chats });
                    window.events.splice(0, events.length);
                    window.chats.splice(0, chats.length);
                    window.lastSync = Date.now();
                    if (events.length || chats.length) {
                        root.dispatchEvent(new CustomEvent('meeting-monitor:synced', { detail: { chats: chats.length > 0 } }));
                    }
                }

                if (window.endedAt && !window.events.length && !window.chats.length) {
                    stage = 'end';
                    await post(root.dataset.endUrl.replace('__MONITOR__', window.id), {
                        observed_at: window.endedAt,
                    });
                    run.windows.shift();
                    root.dispatchEvent(new CustomEvent('meeting-monitor:synced'));
                }
            }

            if (run.stopped && !run.windows.length) {
                finish(run);
            } else {
                monitoringStatus(run);
            }
        } catch (error) {
            if (error.code === 'monitor_expired') {
                const expired = run.windows.shift();
                if (run.window === expired) run.window = null;
                run.hasGap = true;
                monitoringStatus(run);
            } else if (error.status === 422 && stage === 'start'
                && Date.now() - Date.parse(run.windows[0].startedAt) > 60000) {
                // The start time went stale while the server was unreachable; the device clock is fine.
                // Drop this window so the next pump opens a fresh one.
                const stale = run.windows.shift();
                if (run.window === stale) {
                    run.window = null;
                    updateLiveCount(null);
                }
                run.hasGap = true;
                if (run.stopped && !run.windows.length) {
                    finish(run);
                } else {
                    monitoringStatus(run);
                }
            } else if (error.status === 422 && (stage === 'sync' || stage === 'end')) {
                // The server rejected this batch (for example, the device clock drifted). Skip only that
                // batch, or close the window without an end time, so the rest of the session is still saved.
                const window = run.windows[0];
                if (stage === 'sync') {
                    window.events.splice(0, batchSize);
                    window.chats.splice(0, chatBatchSize);
                    window.lastSync = Date.now();
                } else {
                    run.windows.shift();
                }
                run.hasGap = true;
                if (run.stopped && !run.windows.length) {
                    finish(run);
                } else {
                    monitoringStatus(run);
                }
            } else if (error.status >= 400 && error.status < 500 && error.status !== 429) {
                run.error = error.message;
                run.windows = [];
                await leave(run);
                finish(run);
            } else {
                setStatus('Belum terhubung ke server modul. Mencoba menyimpan aktivitas lagi…', 'warning');
            }
        } finally {
            run.sending = false;
        }
    }

    function connectionChanged(run, event) {
        if (currentRun !== run || run.stopped) return;

        if (event.state === 'Connected') {
            run.connected = true;
            run.wasConnected = true;
            startWindow(run);
        } else if (event.state === 'Reconnecting' || (event.state === 'Connecting' && run.connected)) {
            run.connected = false;
            run.hasGap = true;
            closeWindow(run);
        } else if (event.state === 'Closed') {
            if (!run.joined) run.error = 'Zoom ditutup sebelum monitoring dimulai.';
            run.stopped = true;
            run.connected = false;
            leaveButton.disabled = true;
            closeWindow(run);
        }

        monitoringStatus(run);
        void pump(run);
    }

    joinForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (currentRun) return;
        if (!globalThis.crypto?.randomUUID) {
            setStatus('Buka halaman monitoring melalui HTTPS agar Zoom dapat digunakan.', 'warning');
            return;
        }

        const sdk = window.ZoomMtgEmbedded?.default ?? window.ZoomMtgEmbedded;
        if (!sdk?.createClient) {
            setStatus('Komponen Zoom belum tersedia. Muat ulang halaman dan coba lagi.', 'warning');
            return;
        }

        const run = {
            sdk,
            client: null,
            window: null,
            windows: [],
            joined: false,
            connected: false,
            wasConnected: false,
            stopped: false,
            hasGap: false,
            sending: false,
            listeners: {},
            timer: null,
            meetingNumber: null,
            accountRevision: null,
            error: null,
        };
        currentRun = run;
        joinButton.disabled = true;
        setStatus('Menghubungkan ke Zoom…', 'waiting');

        try {
            const { signature, meetingCode: meetingNumber, accountRevision } = await post(root.dataset.joinUrl) ?? {};
            if (!signature || !meetingNumber || !accountRevision) throw new Error('Otorisasi meeting belum tersedia.');
            run.meetingNumber = String(meetingNumber);
            run.accountRevision = accountRevision;

            zoomAppRoot.hidden = false;
            run.client = sdk.createClient();
            await run.client.init({
                zoomAppRoot,
                language: 'id-ID',
                leaveOnPageUnload: true,
            });

            run.listeners = {
                'connection-change': (payload) => connectionChanged(run, payload),
                'user-added': (payload) => capture(run, 'join', payload),
                'user-removed': (payload) => capture(run, 'leave', payload),
                'user-updated': (payload) => capture(run, 'camera', payload),
                'chat-on-message': (payload) => captureChat(run, payload),
            };
            for (const [name, callback] of Object.entries(run.listeners)) {
                run.client.on(name, callback);
            }

            run.timer = setInterval(() => void pump(run), 2500);
            leaveButton.hidden = false;
            await run.client.join({
                signature,
                meetingNumber: run.meetingNumber,
                userName: root.dataset.displayName,
                password: passcode.value,
            });
            if (currentRun !== run || run.stopped) return;

            run.joined = true;
            passcode.value = '';
            monitoringStatus(run);
            void pump(run);
        } catch (error) {
            if (currentRun !== run) return;

            run.error = error instanceof Error
                ? error.message
                : 'Gagal masuk ke Zoom. Periksa kode akses, lalu coba lagi.';
            if (run.windows.length) {
                await leave(run);
            } else {
                finish(run);
            }
        }
    });

    leaveButton.addEventListener('click', () => {
        if (currentRun) void leave(currentRun);
    });

    // Close the open session when the page goes away, so a reload can start monitoring again at once
    // instead of waiting for the abandoned session to time out.
    window.addEventListener('pagehide', (event) => {
        if (event.persisted || !currentRun) return;

        const observedAt = new Date().toISOString();
        for (const window of currentRun.windows) {
            if (!window.opened) continue;
            fetch(root.dataset.endUrl.replace('__MONITOR__', window.id), {
                method: 'POST',
                keepalive: true,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrfToken,
                },
                body: JSON.stringify({ observed_at: window.endedAt ?? observedAt }),
            }).catch(() => {});
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (!currentRun?.window && !currentRun?.windows.length) return;
        event.preventDefault();
        event.returnValue = '';
    });
})();
