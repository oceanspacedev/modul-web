<?php

namespace App\Http\Requests;

use App\Models\OdDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tambah/ubah dokumen di Approval Dokumen OD.
 */
class OdDocumentRequest extends FormRequest
{
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
            'title' => ['required', 'string', 'max:250'],
            'jenis' => ['required', Rule::in(OdDocument::JENIS)],
            'badan_usaha' => ['nullable', 'string', 'max:120'],
            'pic_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
            'od_document_status_id' => ['required', 'integer', Rule::exists('od_document_statuses', 'id')],
            'catatan' => ['nullable', 'string', 'max:300'],
            'stage' => ['nullable', Rule::in(array_keys(OdDocument::STAGES))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul dokumen',
            'jenis' => 'jenis dokumen',
            'badan_usaha' => 'badan usaha',
            'pic_user_id' => 'PIC',
            'od_document_status_id' => 'status',
            'catatan' => 'catatan',
            'stage' => 'tahap',
        ];
    }
}
