<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubDivisiRequest extends FormRequest
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
            'divisi_id' => ['required', 'integer', Rule::exists('divisis', 'id')->withoutTrashed()],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_divisis', 'name')
                    ->where('divisi_id', $this->input('divisi_id'))
                    ->ignore($this->route('subdivisi'))
                    ->withoutTrashed(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['divisi_id' => 'divisi', 'name' => 'nama sub divisi'];
    }
}
