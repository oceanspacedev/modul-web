<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Authorization is handled by UserPolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => trim((string) $this->input('username')),
            'full_name' => mb_strtoupper(trim((string) $this->input('full_name'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', $this->uniqueUsername()],
            'full_name' => ['required', 'string', 'max:255'],
            'id_karyawan' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'no_wa' => ['nullable', 'string', 'max:25'],
            'password' => $this->passwordRules(),
            'divisi_id' => ['required', 'integer', Rule::exists('divisis', 'id')->withoutTrashed()],
            'sub_divisi_id' => ['nullable', 'integer', Rule::exists('sub_divisis', 'id')->where('divisi_id', $this->input('divisi_id'))->withoutTrashed()],
            'job_level_id' => ['required', 'integer', Rule::exists('job_levels', 'id')->withoutTrashed(), $this->protectAdministratorValue(1)],
            'role' => [$this->roleRequirement(), 'string', Rule::exists('roles', 'name'), $this->protectAdministratorValue('Admin')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'nama lengkap',
            'id_karyawan' => 'ID karyawan',
            'no_wa' => 'nomor WhatsApp',
            'divisi_id' => 'divisi',
            'sub_divisi_id' => 'sub divisi',
            'job_level_id' => 'job level',
        ];
    }

    protected function uniqueUsername(): mixed
    {
        return Rule::unique('users', 'username');
    }

    /**
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::defaults()];
    }

    protected function roleRequirement(): string
    {
        return 'required';
    }

    /**
     * Only administrators may hand out administrator access (the Admin role or job level 1),
     * otherwise anyone with "manage-users" could promote themselves.
     */
    protected function protectAdministratorValue(string|int $protected): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($protected) {
            if ((string) $value === (string) $protected && ! $this->user()->isAdministrator()) {
                $fail('Hanya administrator yang dapat memberikan akses administrator.');
            }
        };
    }
}
