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

        <form action="{{ route('ai-settings.update') }}" method="POST" id="ai-settings-form" class="m-0">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header"><h3 class="card-title">Provider aktif</h3></div>
                <div class="card-body">
                    <div class="d-flex flex-wrap">
                        @foreach ($providers as $key => $item)
                            <div class="custom-control custom-radio mr-4 mb-1">
                                <input type="radio" class="custom-control-input" id="provider_{{ $key }}" name="provider" value="{{ $key }}" @checked(old('provider', $activeProvider) === $key)>
                                <label class="custom-control-label" for="provider_{{ $key }}">{{ $item['provider']->label() }}</label>
                            </div>
                        @endforeach
                    </div>
                    @error('provider')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <p class="small mb-0 mt-2 {{ $configured ? 'text-success' : 'text-danger' }}">
                        <i class="fas {{ $configured ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
                        {{ $configured ? 'AI aktif.' : 'AI belum aktif: isi API key dan model provider aktif.' }}
                        <span class="text-muted">Video hanya bisa dibaca Gemini.</span>
                    </p>
                </div>
            </div>

            <div class="row">
                @foreach ($providers as $key => $item)
                    @php($provider = $item['provider'])
                    <div class="col-xl-4 col-md-6">
                        <div class="card" data-ai-provider="{{ $key }}">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title">{{ $provider->label() }}</h3>
                                @if ($key === $activeProvider)
                                    <span class="badge badge-success ml-auto">Aktif</span>
                                @endif
                            </div>
                            <div class="card-body small">
                                <div class="form-group mb-2">
                                    <label for="{{ $key }}_api_key" class="mb-1">API key</label>
                                    <input type="password" id="{{ $key }}_api_key" name="{{ $key }}_api_key" maxlength="500" autocomplete="new-password" data-ai-key
                                        placeholder="{{ $item['maskedKey'] ? 'Tersimpan '.$item['maskedKey'] : 'Tempel API key' }}"
                                        class="form-control form-control-sm @error($key.'_api_key') is-invalid @enderror">
                                    @error($key.'_api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="d-flex justify-content-between mt-1">
                                        <span class="text-muted">
                                            @if ($item['source'] === 'pengaturan')
                                                Kosongkan agar tetap
                                            @elseif ($item['source'] === 'env')
                                                Memakai key dari .env
                                            @elseif (! $item['source'])
                                                Belum ada key
                                            @endif
                                        </span>
                                        <a href="{{ $provider->keyUrl() }}" target="_blank" rel="noopener">Buat key <i class="fas fa-external-link-alt"></i></a>
                                    </div>
                                    @if ($item['maskedKey'])
                                        <div class="custom-control custom-checkbox mt-1">
                                            <input type="checkbox" class="custom-control-input" id="{{ $key }}_forget" name="{{ $key }}_forget" value="1" @checked(old($key.'_forget'))>
                                            <label class="custom-control-label" for="{{ $key }}_forget">Hapus key tersimpan</label>
                                        </div>
                                    @endif
                                </div>

                                <div class="form-group mb-0">
                                    <label for="{{ $key }}_model" class="mb-1">Model</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="{{ $key }}_model" name="{{ $key }}_model" maxlength="120" list="{{ $key }}_models" data-ai-model
                                            value="{{ old($key.'_model', $item['model']) }}"
                                            placeholder="{{ $provider->defaultModel() ?: 'Pilih atau ketik nama model' }}"
                                            class="form-control @error($key.'_model') is-invalid @enderror">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-default" data-ai-load data-url="{{ route('ai-settings.models', $key) }}">
                                                <i class="fas fa-sync-alt"></i> Muat model
                                            </button>
                                        </div>
                                        @error($key.'_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <datalist id="{{ $key }}_models"></datalist>
                                    <div class="text-muted mt-1" data-ai-status>
                                        @if ($provider->defaultModel())
                                            Kosong = {{ $provider->defaultModel() }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan &amp; Tes Koneksi</button>
        </form>
    </div>
</section>

<script>
    (function () {
        const token = document.querySelector('#ai-settings-form input[name="_token"]').value;

        document.querySelectorAll('[data-ai-load]').forEach(function (button) {
            button.addEventListener('click', async function () {
                const card = button.closest('[data-ai-provider]');
                const status = card.querySelector('[data-ai-status]');
                const list = card.querySelector('datalist');
                const modelInput = card.querySelector('[data-ai-model]');

                button.disabled = true;
                status.className = 'text-muted mt-1';
                status.textContent = 'Memuat model...';

                try {
                    const response = await fetch(button.dataset.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ api_key: card.querySelector('[data-ai-key]').value }),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(data.message || 'Gagal memuat model (kesalahan ' + response.status + ').');
                    }

                    list.replaceChildren(...data.models.map(function (model) {
                        const option = document.createElement('option');
                        option.value = model.id;
                        option.label = model.label;
                        return option;
                    }));
                    status.className = 'text-success mt-1';
                    status.textContent = data.models.length + ' model tersedia. Klik kolom model untuk memilih.';
                    modelInput.focus();
                } catch (error) {
                    status.className = 'text-danger mt-1';
                    status.textContent = error.message;
                } finally {
                    button.disabled = false;
                }
            });
        });
    })();
</script>
@endsection
