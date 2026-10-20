<?php

namespace App\Http\Requests;

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
            'sub_divisi_id' => ['nullable', 'integer', Rule::exists('sub_divisis', 'id')->where('divisi_id', $this->input('divisi_id'))->withoutTrashed()],
            'job_level_id' => ['required', 'array', 'min:1'],
            'job_level_id.*' => ['integer', Rule::exists('job_levels', 'id')->withoutTrashed()],
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
            'sub_divisi_id' => 'sub divisi',
            'job_level_id' => 'job level',
        ];
    }
}
