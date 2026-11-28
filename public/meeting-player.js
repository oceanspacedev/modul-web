// Pemutar mini meeting: selama monitoring berjalan, pindah halaman tidak memuat ulang halaman ini. Halaman
// tujuan dibuka di iframe layar penuh di atasnya, sedangkan klien meeting tetap hidup di jendela mengambang
// sehingga pencatatan aktivitas terus berjalan. Klien platform (mis. meeting-clients/zoom.js) mengumumkan
// mulai dan selesainya monitoring lewat event `meeting-monitor:started` dan `meeting-monitor:stopped` di
// elemen [data-meeting-monitor], dan menerima ukuran tampilan lewat event `meeting-player:layout`.
(() => {
    const root = document.querySelector('[data-meeting-monitor]');
    const player = root?.querySelector('[data-meeting-player]');
    if (!root || !player) return;

    const FRAME_NAME = 'meeting-player-frame';
    const POSITION_KEY = 'meeting-player-position';
    const SIZE_KEY = 'meeting-player-size';
    const SIZES = { small: [320, 180], large: [480, 270] };

    // Halaman ini sendiri dimuat di dalam iframe pemutar (mis. pengguna membuka halaman aktivitas pelatihan
    // lain): klien kedua tidak boleh ikut bergabung.
    if (framedByPlayer()) {
        root.dataset.joinBlocked = 'Monitoring sedang berjalan di pemutar mini. Selesaikan dulu sebelum memulai monitoring lain.';
        const button = root.querySelector('[data-meeting-join] [type="submit"]');
        if (button) button.disabled = true;
        const status = root.querySelector('[data-meeting-status]');
        if (status) status.textContent = root.dataset.joinBlocked;
        return;
    }

    const meetingUrl = new URL(root.dataset.meetingUrl, location.href);
    const meetingTitle = document.title;
    const status = root.querySelector('[data-meeting-status]');
    const playerStatus = player.querySelector('[data-player-status]');
    const sizeButton = player.querySelector('[data-player-size]');
    const handle = player.querySelector('[data-player-handle]');
    let running = false;
    let finished = false;
    let frame = null;

    function framedByPlayer() {
        try {
            return window.parent !== window && window.name === FRAME_NAME;
        } catch {
            return false;
        }
    }

    function isMeetingPage(url) {
        return url.origin === meetingUrl.origin && url.pathname.replace(/\/$/, '') === meetingUrl.pathname.replace(/\/$/, '');
    }

    function readStorage(key) {
        try {
            return JSON.parse(sessionStorage.getItem(key));
        } catch {
            return null;
        }
    }

    function writeStorage(key, value) {
        try {
            sessionStorage.setItem(key, JSON.stringify(value));
        } catch {
            // Posisi dan ukuran hanya kenyamanan; tanpa penyimpanan, pemutar kembali ke pojok kanan bawah.
        }
    }

    function floating() {
        return frame !== null;
    }

    function size() {
        return SIZES[readStorage(SIZE_KEY)] ? readStorage(SIZE_KEY) : 'small';
    }

    function announceLayout() {
        const [width, height] = SIZES[size()];
        const narrow = window.innerWidth < 576;
        root.dispatchEvent(new CustomEvent('meeting-player:layout', {
            detail: floating()
                ? { floating: true, width: narrow ? Math.min(width, window.innerWidth - 24) : width, height: narrow ? Math.round(Math.min(width, window.innerWidth - 24) * 9 / 16) : height }
                : { floating: false, width: player.clientWidth },
        }));
        // Panel video bawaan SDK diposisikan terhadap wadahnya; resize memaksanya menghitung ulang posisi.
        window.dispatchEvent(new Event('resize'));
    }

    function clampPosition(position) {
        const rect = player.getBoundingClientRect();
        const maxX = Math.max(8, window.innerWidth - rect.width - 8);
        const maxY = Math.max(8, window.innerHeight - rect.height - 8);
        return {
            x: Math.min(Math.max(8, position.x), maxX),
            y: Math.min(Math.max(8, position.y), maxY),
        };
    }

    function place(position) {
        if (!floating()) return;
        if (!position || window.innerWidth < 576) {
            player.style.left = '';
            player.style.top = '';
            return;
        }
        const { x, y } = clampPosition(position);
        player.style.left = `${x}px`;
        player.style.top = `${y}px`;
    }

    function applySize() {
        player.dataset.size = size();
        if (sizeButton) {
            const large = size() === 'large';
            sizeButton.setAttribute('aria-label', large ? 'Perkecil pemutar' : 'Perbesar pemutar');
            sizeButton.title = sizeButton.getAttribute('aria-label');
            sizeButton.querySelector('i').className = large ? 'fas fa-compress-alt' : 'fas fa-expand-alt';
        }
    }

    function syncFromFrame() {
        let doc;
        let url;
        try {
            doc = frame.contentDocument;
            url = new URL(frame.contentWindow.location.href);
        } catch {
            return;
        }
        if (!doc || url.href === 'about:blank') return;

        // Kembali ke halaman monitoring ini: pemutar kembali ke tempatnya, dengan isi riwayat terbaru.
        if (isMeetingPage(url)) {
            dock(url, doc);
            return;
        }

        // Halaman tanpa menu (login, galeri publik) berarti sesi pengguna sudah berakhir.
        if (!doc.body?.classList.contains('sidebar-mini')) {
            location.replace(url.href);
            return;
        }

        document.title = doc.title || meetingTitle;
        try {
            history.replaceState({ meetingPlayer: true, url: url.href }, '', url.href);
        } catch {
            // Alamat di bilah browser hanya penanda; navigasi tetap berjalan di iframe.
        }

        doc.addEventListener('click', (event) => interceptFrameClick(event, doc), true);
    }

    // Di dalam iframe: tautan ke halaman monitoring ini langsung mengembalikan pemutar tanpa memuat ulang klien
    // meeting di iframe, dan tautan ke situs lain dibuka di tab baru karena tidak bisa ditampilkan di iframe.
    function interceptFrameClick(event, doc) {
        const link = navigableLink(event);
        if (!link) return;

        const url = new URL(link.href, doc.baseURI);
        if (url.origin !== location.origin) {
            link.target = '_blank';
            link.rel = 'noopener';
        } else if (isMeetingPage(url)) {
            event.preventDefault();
            dock(url);
        }
    }

    function navigableLink(event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return null;
        const link = event.target.closest?.('a[href]');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return null;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.dataset.toggle || link.dataset.widget) return null;
        return link;
    }

    function open(url) {
        if (!frame) {
            frame = document.createElement('iframe');
            frame.name = FRAME_NAME;
            frame.className = 'meeting-player-frame';
            frame.title = 'Halaman aplikasi';
            frame.addEventListener('load', syncFromFrame);
            document.body.append(frame);
            document.documentElement.classList.add('has-meeting-player-frame');
            player.classList.add('is-floating');
            applySize();
            place(readStorage(POSITION_KEY));
            announceLayout();
            try {
                history.pushState({ meetingPlayer: true, url: url?.href ?? meetingUrl.href }, '', url?.href ?? meetingUrl.href);
            } catch {
                // Tombol kembali browser tidak akan menutup pemutar, tetapi navigasi tetap berjalan.
            }
        }
        if (url) frame.src = url.href;
        return frame;
    }

    async function dock(url = meetingUrl, source = null) {
        if (frame) {
            frame.remove();
            frame = null;
            document.documentElement.classList.remove('has-meeting-player-frame');
            player.classList.remove('is-floating');
            player.style.left = '';
            player.style.top = '';
            document.title = meetingTitle;
            try {
                history.replaceState(null, '', url.href);
            } catch {
                // Hanya alamat di bilah browser.
            }
            announceLayout();
        }
        if (finished) {
            location.replace(url.href);
            return;
        }
        await refresh(url, source);
    }

    // Perbarui bagian halaman yang berubah (pesan, riwayat aktivitas, chat) tanpa memuat ulang klien meeting.
    async function refresh(url, source = null) {
        let doc = source;
        if (!doc) {
            try {
                const response = await fetch(url.href, { headers: { Accept: 'text/html' } });
                if (!response.ok || response.redirected) return;
                doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            } catch {
                return;
            }
        }
        for (const target of document.querySelectorAll('[data-meeting-refresh]')) {
            const next = doc.querySelector(`[data-meeting-refresh="${target.dataset.meetingRefresh}"]`);
            if (!next) continue;
            target.innerHTML = next.innerHTML;
            if (next.dataset.url) target.dataset.url = next.dataset.url;
        }
        try {
            history.replaceState(null, '', url.href);
        } catch {
            // Hanya alamat di bilah browser.
        }
        if (url.hash) document.querySelector(url.hash)?.scrollIntoView();
    }

    function finish() {
        finished = true;
        player.classList.add('is-finished');
        if (!floating()) player.classList.remove('is-active');
    }

    // Selama monitoring, tautan di halaman ini dibuka di iframe agar halaman (dan klien meeting) tidak dimuat ulang.
    document.addEventListener('click', (event) => {
        if (!running || finished) return;
        const link = navigableLink(event);
        if (!link || player.contains(link)) return;

        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;

        event.preventDefault();
        if (isMeetingPage(url)) {
            void dock(url);
        } else {
            open(url);
        }
    });

    document.addEventListener('submit', (event) => {
        if (!running || finished || event.defaultPrevented) return;
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || root.querySelector('[data-meeting-join]') === form) return;
        if (form.target && form.target !== '_self') return;

        const url = new URL(form.getAttribute('action') || location.href, location.href);
        if (url.origin !== location.origin) return;

        if ((form.method || 'get').toLowerCase() === 'get' && isMeetingPage(url)) {
            event.preventDefault();
            const query = new URLSearchParams(new FormData(form));
            url.search = query.toString();
            void refresh(url);
            return;
        }

        // Kiriman lain (mis. impor chat, tandai notifikasi) diarahkan ke iframe. Bila hasilnya kembali ke
        // halaman ini, pemutar kembali ke tempatnya dan pesannya ikut tampil.
        open();
        form.target = FRAME_NAME;
        setTimeout(() => form.removeAttribute('target'));
    });

    window.addEventListener('popstate', (event) => {
        if (!running && !frame) return;
        if (event.state?.meetingPlayer && !finished) {
            open(new URL(event.state.url));
        } else if (frame) {
            void dock();
        }
    });

    root.addEventListener('meeting-monitor:started', () => {
        running = true;
        finished = false;
        player.classList.add('is-active');
        player.classList.remove('is-finished');
    });

    root.addEventListener('meeting-monitor:stopped', finish);

    player.querySelector('[data-player-expand]')?.addEventListener('click', () => void dock());

    player.querySelector('[data-player-leave]')?.addEventListener('click', () => {
        if (!window.confirm('Akhiri monitoring dan keluar dari meeting?')) return;
        root.querySelector('[data-meeting-leave]')?.click();
    });

    // Setelah monitoring selesai, tutup pemutar dan tampilkan halaman yang sedang dibuka sebagai halaman biasa.
    player.querySelector('[data-player-close]')?.addEventListener('click', () => {
        let url = meetingUrl.href;
        try {
            url = frame?.contentWindow.location.href ?? url;
        } catch {
            // Pakai halaman monitoring bila alamat iframe tidak terbaca.
        }
        location.replace(url);
    });

    sizeButton?.addEventListener('click', () => {
        writeStorage(SIZE_KEY, size() === 'large' ? 'small' : 'large');
        applySize();
        place(readStorage(POSITION_KEY));
        announceLayout();
    });

    // Geser pemutar dari bilah judulnya.
    handle?.addEventListener('pointerdown', (event) => {
        if (!floating() || event.button !== 0 || event.target.closest('button') || window.innerWidth < 576) return;
        event.preventDefault();
        const rect = player.getBoundingClientRect();
        const offsetX = event.clientX - rect.left;
        const offsetY = event.clientY - rect.top;
        handle.setPointerCapture(event.pointerId);
        player.classList.add('is-dragging');

        const move = (moveEvent) => place({ x: moveEvent.clientX - offsetX, y: moveEvent.clientY - offsetY });
        const end = () => {
            handle.removeEventListener('pointermove', move);
            handle.removeEventListener('pointerup', end);
            handle.removeEventListener('pointercancel', end);
            player.classList.remove('is-dragging');
            const placed = player.getBoundingClientRect();
            writeStorage(POSITION_KEY, { x: placed.left, y: placed.top });
            announceLayout();
        };
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', end);
        handle.addEventListener('pointercancel', end);
    });

    window.addEventListener('resize', (event) => {
        if (!event.isTrusted || !floating()) return;
        place(readStorage(POSITION_KEY));
    });

    if (status && playerStatus) {
        const mirror = () => {
            playerStatus.textContent = status.textContent;
            playerStatus.dataset.state = status.dataset.state ?? '';
        };
        new MutationObserver(mirror).observe(status, { childList: true, characterData: true, subtree: true, attributes: true });
        mirror();
    }
})();
