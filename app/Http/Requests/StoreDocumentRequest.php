<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Authorization is handled by DocumentPolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'], // 50 MB
            'change_note' => ['nullable', 'string', 'max:500'],
            'document_type' => ['required', 'integer', Rule::exists('dokumen_types', 'id')->withoutTrashed()],
            'divisi_id' => ['required', 'integer', Rule::exists('divisis', 'id')->withoutTrashed()],
            'role_id' => ['required', 'array', 'min:1'],
            'role_id.*' => ['integer', $this->validTargetRole()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'file dokumen',
            'change_note' => 'catatan perubahan',
            'document_type' => 'tipe dokumen',
            'divisi_id' => 'divisi',
            'role_id' => 'jabatan',
            'role_id.*' => 'jabatan',
        ];
    }

    /**
     * 0 berarti untuk semua jabatan.
     */
    protected function validTargetRole(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ((int) $value !== 0 && ! Role::whereKey($value)->exists()) {
                $fail('Jabatan yang dipilih tidak valid.');
            }
        };
    }
}
