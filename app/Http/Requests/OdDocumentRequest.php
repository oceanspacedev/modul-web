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
        /** @var OdDocument|null $document */
        $document = $this->route('document');

        return [
            'title' => ['required', 'string', 'max:250'],
            'jenis' => ['required', Rule::exists('document_categories', 'code')],
            'badan_usaha' => ['nullable', 'string', 'max:120'],
            'od_document_status_id' => ['required', 'integer', Rule::exists('od_document_statuses', 'id')],
            'catatan' => ['nullable', 'string', 'max:300'],
            'file' => ['nullable', ...OdDocument::FILE_RULES],
            // Tahap hanya untuk dokumen dari Permintaan Dokumen, dari tahapan kategorinya.
            'stage' => ['nullable', Rule::in($document?->stageNames() ?? [])],
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
            'od_document_status_id' => 'status',
            'catatan' => 'catatan',
            'file' => 'file dokumen',
            'stage' => 'tahap',
        ];
    }
}
