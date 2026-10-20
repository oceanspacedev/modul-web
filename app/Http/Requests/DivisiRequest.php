<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DivisiRequest extends FormRequest
{
    /**
     * Authorization is handled by the "can:manage-master-data" route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => mb_strtoupper(trim((string) $this->input('name')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('divisis', 'name')->ignore($this->route('divisi'))->withoutTrashed()],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama divisi',
            'area_id' => 'area',
            'manager_id' => 'kepala divisi',
        ];
    }
}
