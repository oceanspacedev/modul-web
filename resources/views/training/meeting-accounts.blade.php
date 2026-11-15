@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Akun Meeting" :breadcrumbs="['Pelatihan' => route('training.index'), 'Akun Meeting' => null]">
            <x-slot:actions>
                <a href="{{ route('training.index') }}" class="btn btn-default">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Jadwal pelatihan
                </a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="row">
            <section class="col-lg-5" aria-labelledby="add-meeting-account">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5" id="add-meeting-account">Tambah akun penyelenggara</h2>
                        <p class="text-muted">Akun dipakai untuk mencatat aktivitas peserta di meeting pelatihan dan bisa dipilih lagi saat membuat pelatihan lain.</p>
                        <form method="POST" action="{{ route('training.meeting-accounts.store') }}" id="meeting-account-form">
                            @csrf
                            <div class="form-group">
                                <label for="meeting-account-provider">Platform</label>
                                <select id="meeting-account-provider" class="form-control" name="provider" required>
                                    @foreach($providers as $key => $provider)
                                        <option value="{{ $key }}" @selected(old('provider') === $key)>{{ $provider->label() }}</option>
                                    @endforeach
                                </select>
                                @error('provider')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                            <div class="form-group">
                                <label for="meeting-account-name">Nama akun</label>
                                <input id="meeting-account-name" class="form-control" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Contoh: Zoom Tim OD" required>
                                @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                            @foreach($providers as $key => $provider)
                                <fieldset data-provider-credentials="{{ $key }}">
                                    @if($key === 'zoom')
                                        <p class="text-muted small">Gunakan kredensial General App dengan Meeting SDK dari akun Zoom yang mengadakan pelatihan.
                                            <a href="https://developers.zoom.us/docs/meeting-sdk/get-credentials/" target="_blank" rel="noopener noreferrer">Panduan kredensial Zoom</a>
                                        </p>
                                    @endif
                                    @foreach($provider->credentialFields() as $field => $definition)
                                        <div class="form-group">
                                            <label for="meeting-account-{{ $key }}-{{ $field }}">{{ $definition['label'] }}</label>
                                            @if($definition['multiline'] ?? false)
                                                <textarea id="meeting-account-{{ $key }}-{{ $field }}" class="form-control" name="credentials[{{ $field }}]" rows="4"
                                                    maxlength="{{ $definition['max'] ?? 512 }}" autocomplete="off" autocapitalize="off" spellcheck="false" required></textarea>
                                            @else
                                                <input id="meeting-account-{{ $key }}-{{ $field }}" class="form-control" name="credentials[{{ $field }}]" maxlength="{{ $definition['max'] ?? 512 }}"
                                                    type="{{ $definition['secret'] ? 'password' : 'text' }}" autocomplete="{{ $definition['secret'] ? 'new-password' : 'off' }}"
                                                    autocapitalize="off" spellcheck="false" required>
                                            @endif
                                            @isset($definition['help'])<small class="form-text text-muted">{{ $definition['help'] }}</small>@endisset
                                            @error("credentials.$field")<small class="text-danger">{{ $message }}</small>@enderror
                                        </div>
                                    @endforeach
                                </fieldset>
                            @endforeach
                            <button type="submit" class="btn btn-primary">Simpan akun</button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="col-lg-7" aria-labelledby="meeting-saved-accounts">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5" id="meeting-saved-accounts">Akun tersimpan</h2>
                        @forelse($accounts as $account)
                            @php($accountProvider = $providers[$account->provider] ?? null)
                            <details class="border-top py-3">
                                <summary>{{ $account->name }} <span class="text-muted small">· {{ $accountProvider?->label() ?? $account->provider }}</span></summary>
                                @if($accountProvider)
                                    <form method="POST" action="{{ route('training.meeting-accounts.update', $account) }}" class="mt-3">
                                        @csrf
                                        @method('PATCH')
                                        <div class="form-group">
                                            <label for="meeting-account-name-{{ $account->id }}">Nama akun</label>
                                            <input id="meeting-account-name-{{ $account->id }}" class="form-control" name="name" value="{{ $account->name }}" maxlength="100" required>
                                        </div>
                                        <p class="text-muted">Isi semua kolom di bawah jika ingin mengganti kredensial.</p>
                                        @foreach($accountProvider->credentialFields() as $field => $definition)
                                            <div class="form-group">
                                                <label for="meeting-account-{{ $field }}-{{ $account->id }}">{{ $definition['label'] }} baru</label>
                                                @if($definition['multiline'] ?? false)
                                                    <textarea id="meeting-account-{{ $field }}-{{ $account->id }}" class="form-control" name="credentials[{{ $field }}]" rows="4"
                                                        maxlength="{{ $definition['max'] ?? 512 }}" autocomplete="off" autocapitalize="off" spellcheck="false"></textarea>
                                                @else
                                                    <input id="meeting-account-{{ $field }}-{{ $account->id }}" class="form-control" name="credentials[{{ $field }}]" maxlength="{{ $definition['max'] ?? 512 }}"
                                                        type="{{ $definition['secret'] ? 'password' : 'text' }}" autocomplete="{{ $definition['secret'] ? 'new-password' : 'off' }}"
                                                        autocapitalize="off" spellcheck="false">
                                                @endif
                                            </div>
                                        @endforeach
                                        <button type="submit" class="btn btn-default btn-sm">Simpan perubahan</button>
                                    </form>
                                @endif
                                @if($account->trainings_exists)
                                    <p class="mt-3 mb-0 text-muted small">Akun masih dipakai pada pelatihan.</p>
                                @else
                                    <form method="POST" action="{{ route('training.meeting-accounts.destroy', $account) }}" class="mt-3" onsubmit="return confirm('Hapus akun ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Hapus akun</button>
                                    </form>
                                @endif
                            </details>
                        @empty
                            <p class="text-muted mb-0">Belum ada akun yang tersimpan.</p>
                        @endforelse
                        {{ $accounts->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<script>
    (() => {
        // Hanya kolom kredensial platform terpilih yang ikut terkirim.
        const provider = document.getElementById('meeting-account-provider');
        const groups = document.querySelectorAll('[data-provider-credentials]');
        function update() {
            for (const group of groups) {
                const active = group.dataset.providerCredentials === provider.value;
                group.hidden = !active;
                group.disabled = !active;
            }
        }
        provider.addEventListener('change', update);
        update();
    })();
</script>
@endsection
