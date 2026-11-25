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

        <form action="{{ route('ai-settings.update') }}" method="POST" id="ai-settings-form" class="m-0" style="max-width: 640px;">
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
                    data-models-url="{{ route('ai-settings.models', $key) }}">

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
                                    <button type="button" class="btn btn-default" data-ai-test><i class="fas fa-plug"></i> Tes API key</button>
                                </div>
                                @error($key.'_api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <span data-ai-status class="text-muted">
                                    @if ($item['source'] === 'pengaturan')
                                        Kosongkan untuk memakai key tersimpan.
                                    @endif
                                </span>
                                <a href="{{ $provider->keyUrl() }}" target="_blank" rel="noopener" class="text-nowrap ml-2">Buat key <i class="fas fa-external-link-alt"></i></a>
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

            <button type="submit" class="btn btn-primary" data-ai-save><i class="fas fa-save"></i> Simpan</button>
            <span class="small text-muted ml-2" data-ai-next></span>
        </form>
    </div>
</section>

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
            status.className = 'text-muted';
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

                status.className = 'text-success';
                status.textContent = 'API key valid. ' + data.models.length + ' model tersedia.';
                showModels(panel, data.models);
            } catch (error) {
                status.className = 'text-danger';
                status.textContent = error.message;
                hideModels(panel);
            } finally {
                button.disabled = false;
            }
        }

        panels.forEach(function (panel) {
            panel.querySelector('[data-ai-test]').addEventListener('click', () => test(panel));
            // Key diganti: modelnya harus dicek ulang dengan key baru.
            panel.querySelector('[data-ai-key]').addEventListener('input', function () {
                panel.dataset.tested = '';
                hideModels(panel);
            });
        });
        choices.forEach(choice => choice.addEventListener('change', () => showPanel(choice.value)));
        form.addEventListener('input', refreshSave);

        // Setelah validasi gagal, biarkan isian apa adanya (termasuk kolom model) agar pesan error terlihat.
        if (hasErrors) {
            panels.forEach(panel => panel.dataset.tested = '1');
        }
        showPanel(selected());
    })();
</script>
@endsection
