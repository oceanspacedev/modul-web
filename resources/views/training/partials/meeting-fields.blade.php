@php
    use App\Support\Meetings\MeetingProviders;

    // Pilihan kosong ("Tatap muka", tanpa akun) tersimpan sebagai null di old input, jadi setelah validasi gagal
    // nilai tersimpan tidak boleh dipakai lagi sebagai cadangan.
    $resubmitted = session()->hasOldInput();
    $platform = $resubmitted ? old('meeting_platform') : $training?->meetingPlatform();
    $meetingLink = $resubmitted ? old('meeting_link') : $training?->meeting_link;
    $accountId = (string) ($resubmitted ? old('meeting_account_id') : $training?->meeting_account_id);
    $monitoringEnabled = $resubmitted ? old('meeting_monitoring_enabled') : $training?->meeting_monitoring_enabled;
    $providers = MeetingProviders::all();
    $monitorable = (bool) ($providers[$platform] ?? null)?->hasIntegration();
@endphp

<div id="training-meeting-fields">
    <div class="form-group">
        <label for="meeting_platform">Platform pelatihan</label>
        <select id="meeting_platform" name="meeting_platform" class="form-control">
            <option value="">Tatap muka</option>
            @foreach($providers as $value => $provider)
                <option value="{{ $value }}" data-label="{{ $provider->label() }}"
                    data-monitoring="{{ $provider->hasIntegration() ? '1' : '0' }}" @selected($platform === $value)>{{ $provider->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group" data-meeting-link @if(!$platform) hidden @endif>
        <label for="meeting_link">Tautan meeting</label>
        <input type="url" id="meeting_link" name="meeting_link" class="form-control" maxlength="2048"
            value="{{ $meetingLink }}" placeholder="https://…" @disabled(!$platform) @required($platform)>
    </div>

    {{-- Hanya untuk platform yang punya integrasi (pemantauan langsung atau tarik kehadiran). --}}
    <fieldset data-meeting-monitoring @if(!$monitorable) hidden disabled @endif>
        <div class="custom-control custom-switch mb-3">
            <input type="hidden" name="meeting_monitoring_enabled" value="0">
            <input type="checkbox" class="custom-control-input" id="meeting_monitoring_enabled"
                name="meeting_monitoring_enabled" value="1" @checked($monitoringEnabled)>
            <label class="custom-control-label" for="meeting_monitoring_enabled">Aktifkan monitoring <span data-platform-label>{{ $monitorable ? $providers[$platform]->label() : '' }}</span></label>
        </div>

        <div class="form-group">
            <label for="meeting_account_id">Akun <span data-platform-label>{{ $monitorable ? $providers[$platform]->label() : '' }}</span> penyelenggara</label>
            <select id="meeting_account_id" name="meeting_account_id" class="form-control" aria-describedby="training-meeting-account-help">
                <option value="">Pilih akun</option>
                @foreach($meetingAccounts as $account)
                    <option value="{{ $account->id }}" data-provider="{{ $account->provider }}"
                        data-owned="{{ $account->user_id === auth()->id() ? '1' : '0' }}"
                        @selected($accountId === (string) $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            <small class="form-text text-muted" id="training-meeting-account-help">
                Wajib dipilih untuk monitoring. <a href="{{ route('training.meeting-accounts.index') }}" target="_blank" rel="noopener" data-manage-meeting-accounts>Kelola akun meeting</a>
            </small>
        </div>
    </fieldset>
</div>

<script>
    (() => {
        const fields = document.getElementById('training-meeting-fields');
        const platform = fields.querySelector('#meeting_platform');
        const link = fields.querySelector('#meeting_link');
        const monitoring = fields.querySelector('[data-meeting-monitoring]');
        const enabled = fields.querySelector('#meeting_monitoring_enabled');
        const account = fields.querySelector('#meeting_account_id');
        const manage = fields.querySelector('[data-manage-meeting-accounts]');
        let refreshAccounts = false;

        function updateFields() {
            const selected = platform.selectedOptions[0];
            const isOnline = platform.value !== '';
            const canMonitor = selected?.dataset.monitoring === '1';
            link.closest('[data-meeting-link]').hidden = !isOnline;
            link.disabled = !isOnline;
            link.required = isOnline;
            monitoring.hidden = !canMonitor;
            monitoring.disabled = !canMonitor;
            for (const label of monitoring.querySelectorAll('[data-platform-label]')) {
                label.textContent = canMonitor ? selected.dataset.label : '';
            }

            // Akun hanya bisa dipakai pada platformnya sendiri.
            for (const option of account.options) {
                if (option.value === '') continue;
                const matches = option.dataset.provider === platform.value;
                option.hidden = !matches;
                option.disabled = !matches;
            }
            if (account.selectedOptions[0]?.disabled) account.value = '';
            if (typeof $ !== 'undefined' && $(account).hasClass('select2-hidden-accessible')) {
                $(account).trigger('change.select2');
            }
            account.required = canMonitor && enabled.checked;
        }

        async function updateAccounts() {
            if (!refreshAccounts || document.hidden) return;
            refreshAccounts = false;
            try {
                const response = await fetch(manage.href, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const { accounts } = await response.json();
                const selected = account.value;
                const otherAccounts = [...account.options].filter(option => option.dataset.owned === '0');
                const ownAccounts = accounts.map(({ id, name, provider }) => {
                    const option = new Option(name, String(id));
                    option.dataset.owned = '1';
                    option.dataset.provider = provider;
                    return option;
                });
                account.replaceChildren(account.options[0], ...otherAccounts, ...ownAccounts);
                account.value = [...account.options].some(option => option.value === selected) ? selected : '';
                updateFields();
            } catch {
                refreshAccounts = true;
            }
        }

        platform.addEventListener('change', updateFields);
        enabled.addEventListener('change', updateFields);
        manage.addEventListener('click', () => { refreshAccounts = true; });
        window.addEventListener('focus', updateAccounts);
        document.addEventListener('visibilitychange', updateAccounts);
        updateFields();
    })();
</script>
