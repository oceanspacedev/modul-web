@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Pengaturan AI" subtitle="Provider, API key dan model" eyebrow="Sistem" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <p class="small mb-3 {{ $configured ? 'text-success' : 'text-danger' }}">
            <i class="fas {{ $configured ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
            @if ($configured)
                Saat ini: <b>{{ $activeLabel }}</b> · {{ $activeModel }}
            @else
                AI belum aktif.
            @endif
        </p>

        <div class="row">
        <div class="col-xl-7">
        <form action="{{ route('ai-settings.update') }}" method="POST" id="ai-settings-form" class="m-0">
            @csrf
            @method('PUT')

            {{-- Langkah 1 --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title"><span class="badge badge-primary mr-2">1</span>Pilih provider</h3></div>
                <div class="card-body">
                    @foreach ($providers as $key => $item)
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" class="custom-control-input" id="provider_{{ $key }}" name="provider" value="{{ $key }}" data-ai-provider-choice @checked(old('provider', $activeProvider) === $key)>
                            <label class="custom-control-label" for="provider_{{ $key }}">
                                {{ $item['provider']->label() }}
                                @if ($item['provider']->supportsVideo())
                                    <small class="text-muted">· bisa membaca video</small>
                                @endif
                            </label>
                        </div>
                    @endforeach
                    @error('provider')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            @foreach ($providers as $key => $item)
                @php($provider = $item['provider'])
                <div data-ai-panel="{{ $key }}" data-has-key="{{ $item['source'] ? '1' : '0' }}" data-current-model="{{ $item['model'] }}"
                    data-label="{{ $provider->label() }}" data-models-url="{{ route('ai-settings.models', $key) }}" data-chat-url="{{ route('ai-settings.chat', $key) }}">

                    {{-- Langkah 2 --}}
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><span class="badge badge-primary mr-2">2</span>API key {{ $provider->label() }}</h3></div>
                        <div class="card-body small">
                            <div class="input-group input-group-sm">
                                <input type="password" id="{{ $key }}_api_key" name="{{ $key }}_api_key" maxlength="500" autocomplete="new-password" data-ai-key
                                    aria-label="API key {{ $provider->label() }}"
                                    placeholder="{{ $item['maskedKey'] ? 'Tersimpan '.$item['maskedKey'] : ($item['source'] === 'env' ? 'Memakai key dari .env' : 'Tempel API key') }}"
                                    class="form-control @error($key.'_api_key') is-invalid @enderror">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-default btn-sm text-nowrap" data-ai-test><i class="fas fa-plug"></i> Tes API key</button>
                                </div>
                                @error($key.'_api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mt-1">
                                <span data-ai-status class="text-muted min-w-0 text-break">
                                    @if ($item['source'] === 'pengaturan')
                                        Kosongkan untuk memakai key tersimpan.
                                    @endif
                                </span>
                                <a href="{{ $provider->keyUrl() }}" target="_blank" rel="noopener" class="text-nowrap">Buat key <i class="fas fa-external-link-alt"></i></a>
                            </div>
                            @if ($item['maskedKey'])
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" id="{{ $key }}_forget" name="{{ $key }}_forget" value="1" @checked(old($key.'_forget'))>
                                    <label class="custom-control-label" for="{{ $key }}_forget">Hapus key tersimpan</label>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Langkah 3 --}}
                    <div class="card" data-ai-step-model>
                        <div class="card-header"><h3 class="card-title"><span class="badge badge-primary mr-2">3</span>Pilih model</h3></div>
                        <div class="card-body small">
                            <div data-ai-model-slot>
                                <input type="text" id="{{ $key }}_model" name="{{ $key }}_model" maxlength="120" aria-label="Model {{ $provider->label() }}"
                                    value="{{ old($key.'_model', $item['model']) }}" placeholder="Nama model"
                                    class="form-control form-control-sm @error($key.'_model') is-invalid @enderror">
                            </div>
                            @error($key.'_model')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="d-flex flex-wrap align-items-center gap-2 mb-4 mb-xl-0">
                <button type="submit" class="btn btn-primary" data-ai-save><i class="fas fa-save"></i> Simpan</button>
                <span class="small text-muted" data-ai-next></span>
            </div>
        </form>
        </div>

        {{-- Coba chat: memakai provider, key dan model yang sedang dipilih di form (belum perlu disimpan). --}}
        <div class="col-xl-5">
            <div class="card ai-chat" id="ai-chat" hidden>
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="far fa-comments mr-1"></i> Coba chat</h3>
                    <button type="button" class="btn btn-link btn-sm ml-auto p-0" data-chat-clear>Bersihkan</button>
                </div>
                <div class="card-body p-0">
                    <div class="small text-muted px-3 pt-2" data-chat-target></div>
                    <div class="ai-chat-log px-3 py-2" data-chat-log aria-live="polite"></div>
                </div>
                <div class="card-footer">
                    <form class="d-flex gap-2 m-0" data-chat-form>
                        <input type="text" class="form-control form-control-sm" maxlength="2000" autocomplete="off" aria-label="Pesan" data-chat-input>
                        <button type="submit" class="btn btn-primary btn-sm" aria-label="Kirim" data-chat-send><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>
        </div>
    </div>
</section>

<style>
    @media (min-width: 1200px) {
        .ai-chat { position: sticky; top: calc(var(--topbar-height, 57px) + 1rem); }
    }
    [data-ai-status] { min-width: 0; max-width: 100%; overflow-wrap: anywhere; }
    .ai-chat-log { height: 360px; overflow-y: auto; }
    .ai-chat-empty { color: var(--gray-500); font-size: 0.8125rem; text-align: center; margin-top: 140px; }
    .ai-chat-msg { max-width: 85%; margin-bottom: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 12px; font-size: 0.875rem; white-space: pre-wrap; overflow-wrap: anywhere; }
    .ai-chat-msg.is-user { margin-left: auto; background: var(--primary-50); color: var(--gray-950); }
    .ai-chat-msg.is-ai { background: var(--gray-100); color: var(--gray-950); }
    .ai-chat-msg.is-error { background: var(--danger-50); color: var(--danger-700); }
    .ai-chat-msg.is-waiting { color: var(--gray-500); }
    .ai-chat-meta { display: block; margin-top: 0.25rem; color: var(--gray-500); font-size: 0.6875rem; }
    .ai-chat-note { margin: 0.25rem 0 0.75rem; color: var(--gray-500); font-size: 0.75rem; text-align: center; }
</style>

<script>
    (function () {
        const form = document.getElementById('ai-settings-form');
        const token = form.querySelector('input[name="_token"]').value;
        const choices = [...form.querySelectorAll('[data-ai-provider-choice]')];
        const panels = [...form.querySelectorAll('[data-ai-panel]')];
        const save = form.querySelector('[data-ai-save]');
        const next = form.querySelector('[data-ai-next]');
        const hasErrors = {{ $errors->any() ? 'true' : 'false' }};

        const selected = () => choices.find(choice => choice.checked)?.value;
        const panelOf = key => panels.find(panel => panel.dataset.aiPanel === key);
        const modelField = panel => panel.querySelector('[data-ai-model-slot] [name]');

        function setNext(text) {
            next.textContent = text ? 'Berikutnya: ' + text : '';
        }

        function refreshSave() {
            const panel = panelOf(selected());
            const ready = panel && !panel.querySelector('[data-ai-step-model]').hidden && modelField(panel).value !== '';
            save.disabled = !ready;
            if (!panel) {
                setNext('pilih provider.');
            } else if (panel.querySelector('[data-ai-step-model]').hidden) {
                setNext('tes API key.');
            } else {
                setNext(ready ? '' : 'pilih model.');
            }
            updateChat();
        }

        function hideModels(panel) {
            panel.querySelector('[data-ai-step-model]').hidden = true;
            refreshSave();
        }

        // Hanya panel provider terpilih yang tampil dan ikut terkirim.
        function showPanel(key) {
            panels.forEach(function (panel) {
                const active = panel.dataset.aiPanel === key;
                panel.hidden = !active;
                panel.querySelectorAll('input, select').forEach(input => input.disabled = !active);
            });

            const panel = panelOf(key);
            if (panel && panel.dataset.tested !== '1') {
                hideModels(panel);
                if (panel.dataset.hasKey === '1') {
                    test(panel);
                }
            }
            refreshSave();
        }

        function showModels(panel, models) {
            const current = modelField(panel).value || panel.dataset.currentModel;
            const select = document.createElement('select');
            select.name = panel.dataset.aiPanel + '_model';
            select.className = 'form-control form-control-sm';
            select.setAttribute('aria-label', 'Model');
            select.add(new Option('Pilih model...', ''));
            models.forEach(model => select.add(new Option(model.label === model.id ? model.id : model.label + ' (' + model.id + ')', model.id)));
            select.value = models.some(model => model.id === current) ? current : '';

            panel.querySelector('[data-ai-model-slot]').replaceChildren(select);
            panel.querySelector('[data-ai-step-model]').hidden = false;
            panel.dataset.tested = '1';
            refreshSave();
        }

        async function test(panel) {
            const button = panel.querySelector('[data-ai-test]');
            const status = panel.querySelector('[data-ai-status]');

            button.disabled = true;
            status.className = 'text-muted min-w-0 text-break';
            status.textContent = 'Mengecek API key...';

            try {
                const response = await fetch(panel.dataset.modelsUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ api_key: panel.querySelector('[data-ai-key]').value }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || 'Gagal mengecek API key (kesalahan ' + response.status + ').');
                }
                if (!data.models.length) {
                    throw new Error('API key valid, tapi tidak ada model yang bisa dipakai.');
                }

                status.className = 'text-success min-w-0 text-break';
                status.textContent = 'API key valid. ' + data.models.length + ' model tersedia.';
                showModels(panel, data.models);
            } catch (error) {
                status.className = 'text-danger min-w-0 text-break';
                status.textContent = error.message;
                hideModels(panel);
            } finally {
                button.disabled = false;
            }
        }

        // ---- Coba chat ----
        const chat = document.getElementById('ai-chat');
        const chatLog = chat.querySelector('[data-chat-log]');
        const chatTarget = chat.querySelector('[data-chat-target]');
        const chatForm = chat.querySelector('[data-chat-form]');
        const chatInput = chat.querySelector('[data-chat-input]');
        const chatSend = chat.querySelector('[data-chat-send]');
        let history = [];
        let chatSignature = null;
        let chatBusy = false;

        // Provider, key dan model yang sedang dipilih di form; null bila model belum bisa dipilih.
        function chatConfig() {
            const panel = panelOf(selected());
            if (!panel || panel.querySelector('[data-ai-step-model]').hidden || modelField(panel).value === '') {
                return null;
            }

            return {
                panel: panel,
                label: panel.dataset.label,
                model: modelField(panel).value,
                apiKey: panel.querySelector('[data-ai-key]').value,
            };
        }

        function chatBubble(kind, text, meta) {
            chatLog.querySelector('.ai-chat-empty')?.remove();
            const bubble = document.createElement('div');
            bubble.className = 'ai-chat-msg is-' + kind;
            bubble.textContent = text;
            if (meta) {
                const small = document.createElement('span');
                small.className = 'ai-chat-meta';
                small.textContent = meta;
                bubble.appendChild(small);
            }
            chatLog.appendChild(bubble);
            chatLog.scrollTop = chatLog.scrollHeight;

            return bubble;
        }

        function resetChat(note) {
            history = [];
            chatLog.replaceChildren();
            const empty = document.createElement('div');
            empty.className = 'ai-chat-empty';
            empty.textContent = note;
            chatLog.appendChild(empty);
        }

        function updateChat() {
            const config = chatConfig();
            const signature = config ? [config.panel.dataset.aiPanel, config.model, config.apiKey].join('|') : null;

            chatInput.disabled = chatSend.disabled = !config || chatBusy;
            chatInput.placeholder = config ? 'Tulis pesan...' : 'Pilih model dulu (langkah 3)';
            chatTarget.textContent = config ? config.label + ' · ' + config.model : '';

            // Ganti provider, key atau model: percakapan dimulai lagi supaya yang dites jelas.
            if (signature !== chatSignature) {
                chatSignature = signature;
                resetChat(config ? 'Kirim pesan untuk mengecek AI benar-benar menjawab.' : 'Selesaikan langkah 1-3 untuk mencoba chat.');
            }
        }

        chatForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const config = chatConfig();
            const message = chatInput.value.trim();
            if (!config || !message || chatBusy) {
                return;
            }

            chatBubble('user', message);
            chatInput.value = '';
            chatBusy = true;
            updateChat();
            const waiting = chatBubble('waiting', 'Menunggu jawaban...');

            try {
                const response = await fetch(config.panel.dataset.chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ api_key: config.apiKey, model: config.model, message: message, history: history }),
                });
                const data = await response.json().catch(() => ({}));
                waiting.remove();
                if (!response.ok) {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                    throw new Error(firstError || data.message || 'Gagal menghubungi AI (kesalahan ' + response.status + ').');
                }

                chatBubble('ai', data.reply, config.model + ' · ' + (data.ms / 1000).toFixed(1) + ' dtk');
                history.push({ role: 'user', text: message }, { role: 'assistant', text: data.reply });
                history = history.slice(-20);
            } catch (error) {
                waiting.remove();
                chatBubble('error', error.message);
            } finally {
                chatBusy = false;
                updateChat();
                chatInput.focus();
            }
        });

        chat.querySelector('[data-chat-clear]').addEventListener('click', function () {
            chatSignature = null;
            updateChat();
        });
        chat.hidden = false;

        panels.forEach(function (panel) {
            panel.querySelector('[data-ai-test]').addEventListener('click', () => test(panel));
            // Key diganti: modelnya harus dicek ulang dengan key baru.
            panel.querySelector('[data-ai-key]').addEventListener('input', function () {
                panel.dataset.tested = '';
                hideModels(panel);
            });
        });
        choices.forEach(choice => choice.addEventListener('change', () => showPanel(choice.value)));
        // Dropdown model: sebagian browser hanya mengirim "change", bukan "input".
        form.addEventListener('input', refreshSave);
        form.addEventListener('change', refreshSave);

        // Setelah validasi gagal, biarkan isian apa adanya (termasuk kolom model) agar pesan error terlihat.
        if (hasErrors) {
            panels.forEach(panel => panel.dataset.tested = '1');
        }
        showPanel(selected());
    })();
</script>
@endsection
