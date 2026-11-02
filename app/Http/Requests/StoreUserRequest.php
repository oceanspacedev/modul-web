<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalLineService;
use App\Services\ApprovalScopeService;
use App\Services\DataAccessService;
use App\Support\WhatsAppNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Form karyawan mengikuti DnD: identitas, jabatan & approval line, akses data badan usaha, dan login.
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Authorization is handled by UserPolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * User yang sedang diubah; null saat menambah user baru.
     */
    public function record(): ?User
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('no_hp'));

        $this->merge([
            'employee_id' => filled($this->input('employee_id')) ? trim((string) $this->input('employee_id')) : null,
            'nama_lengkap' => Str::upper(Str::squish((string) $this->input('nama_lengkap'))),
            'username' => Str::lower(trim((string) $this->input('username'))),
            'email' => filled($this->input('email')) ? Str::lower(trim((string) $this->input('email'))) : null,
            'no_hp' => $phone === '' ? null : (WhatsAppNumber::toLocal($phone) ?? $phone),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $record = $this->record();

        $rules = [
            'employee_id' => ['nullable', 'string', 'max:255', Rule::unique('users', 'employee_id')->ignore($record)],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id'), $this->areaInScope()],
            'divisi_id' => ['required', 'integer', Rule::exists('divisis', 'id')->withoutTrashed()],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web'), $this->adminRoleAllowed()],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->withoutTrashed()],
            'approval_id' => [
                Rule::requiredIf(fn () => (bool) $this->selectedRole()?->requires_approval),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->withoutTrashed(),
                $this->validApprover(),
            ],
            'trainer' => ['sometimes', 'boolean'],
        ];

        if ($this->canChangeCredentials()) {
            $rules['username'] = ['required', 'string', 'max:255', 'regex:/^\S+$/', Rule::unique('users', 'username')->ignore($record)];
            $rules['password'] = $this->passwordRules();
        }

        if ($this->canChangePhone()) {
            $rules['no_hp'] = ['nullable', 'string', 'max:20', $this->validPhone()];
        }

        if ($this->managesDataAccess()) {
            $rules['data_access'] = ['required', Rule::in([User::DATA_ACCESS_ALL, User::DATA_ACCESS_SELECTED]), $this->dataAccessAllowed()];
            $rules['data_access_areas'] = ['exclude_unless:data_access,'.User::DATA_ACCESS_SELECTED, 'required', 'array', 'min:1'];
            $rules['data_access_areas.*'] = ['integer', Rule::exists('areas', 'id')];

            if (! $this->isGlobalAdminRecord()) {
                $rules['confirm_global_access'] = ['exclude_unless:data_access,'.User::DATA_ACCESS_ALL, 'accepted'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username tidak boleh mengandung spasi.',
            'approval_id.required' => 'Jabatan ini wajib memiliki approval line (atasan).',
            'data_access_areas.required' => 'Pilih minimal satu badan usaha.',
            'confirm_global_access.accepted' => 'Centang konfirmasi untuk memberi akses Semua Badan Usaha.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'ID karyawan',
            'nama_lengkap' => 'nama lengkap',
            'no_hp' => 'No. HP',
            'area_id' => 'area',
            'divisi_id' => 'divisi',
            'role_id' => 'jabatan',
            'position_id' => 'posisi',
            'approval_id' => 'approval line',
            'data_access' => 'cakupan badan usaha',
            'data_access_areas' => 'badan usaha',
        ];
    }

    public function selectedRole(): ?Role
    {
        $roleId = $this->input('role_id');

        return is_numeric($roleId) ? Role::find((int) $roleId) : null;
    }

    /**
     * Akses data hanya berlaku untuk jabatan ADMIN dan hanya diatur super admin.
     */
    public function managesDataAccess(): bool
    {
        return $this->user()->can('manageDataAccess', User::class) && $this->selectedRole()?->name === User::ROLE_ADMIN;
    }

    public function canChangeCredentials(): bool
    {
        $record = $this->record();

        return $record === null || $this->user()->can('changeCredentials', $record);
    }

    public function canChangePhone(): bool
    {
        return $this->user()->can('changePhone', [User::class, $this->record()]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', 'max:255', Password::defaults()];
    }

    protected function isGlobalAdminRecord(): bool
    {
        return DataAccessService::hasGlobalAccess($this->record());
    }

    private function areaInScope(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $currentAreaId = $this->record()?->getOriginal('area_id');

            if ((string) $value !== (string) $currentAreaId && ! DataAccessService::canAccessArea($this->user(), $value)
                && DataAccessService::isAreaScopedAdmin($this->user())) {
                $fail('Area di luar cakupan badan usaha Anda.');
            }
        };
    }

    private function adminRoleAllowed(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $role = $this->selectedRole();
            $record = $this->record();

            if ($role?->name === User::ROLE_ADMIN && ! $this->user()->can('manageDataAccess', User::class)) {
                $fail('Jabatan ADMIN hanya dapat diberikan admin dengan akses seluruh badan usaha.');

                return;
            }

            if ($role?->name !== User::ROLE_ADMIN && $this->isGlobalAdminRecord() && ! DataAccessService::anotherGlobalAdminExists($record)) {
                $fail('Minimal harus ada satu super admin. Jadikan karyawan lain super admin terlebih dahulu.');
            }
        };
    }

    private function dataAccessAllowed(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== User::DATA_ACCESS_SELECTED || ! $this->isGlobalAdminRecord()) {
                return;
            }

            if ($this->user()->is($this->record())) {
                $fail('Anda tidak dapat membatasi akses data akun Anda sendiri.');
            } elseif (! DataAccessService::anotherGlobalAdminExists($this->record())) {
                $fail('Minimal harus ada satu super admin. Jadikan karyawan lain super admin terlebih dahulu.');
            }
        };
    }

    private function validApprover(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $record = $this->record();
            $approvalId = (int) $value;

            if ($record && $approvalId === (int) $record->id) {
                $fail('Approval line tidak boleh dirinya sendiri.');

                return;
            }

            if ($record && ApprovalScopeService::createsApprovalCycle((int) $record->id, $approvalId)) {
                $fail('Approval line membentuk siklus: atasan yang dipilih adalah bawahan karyawan ini.');

                return;
            }

            $selectable = ApprovalLineService::selectableApproverIds($this->user(), $record === null);
            $allowed = $selectable === null
                || in_array($approvalId, $selectable, true)
                || $approvalId === (int) $record?->getOriginal('approval_id')
                || $approvalId === (int) ApprovalLineService::suggest((int) $this->input('divisi_id'), $record?->id)?->id;

            if (! $allowed) {
                $fail('Approval line di luar cakupan Anda.');
            }
        };
    }

    private function validPhone(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $number = WhatsAppNumber::toLocal((string) $value);

            if (! $number) {
                $fail('Format No. HP WhatsApp Indonesia tidak valid, contoh 081234567890.');

                return;
            }

            $exists = User::withTrashed()
                ->where('no_hp', $number)
                ->when($this->record(), fn ($query, User $record) => $query->whereKeyNot($record->id))
                ->exists();

            if ($exists) {
                $fail('No. HP sudah digunakan user lain.');
            }
        };
    }
}
