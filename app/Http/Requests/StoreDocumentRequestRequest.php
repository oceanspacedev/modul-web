<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use App\Support\DocumentRequestRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Form Permintaan Dokumen (publik atau login): data pemohon, kategori dokumen, 1–10 item, dan jadwal observasi bila
 * kategorinya butuh observasi. Item biasa berisi judul, uraian, lampiran opsional (Word, PDF, Excel, Visio), prioritas
 * dan sistem; item kategori bertemplate (mis. Jobdesk) berisi judul, keterangan baru/revisi, prioritas, dan template
 * yang sudah diisi. Aturannya ada di DocumentRequestRules, dipakai bersama API helpdesk.
 */
class StoreDocumentRequestRequest extends FormRequest
{
    private ?DocumentCategory $resolvedCategory = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = DocumentRequestRules::normalize($this->only(['whatsapp', 'email']), $this->category());

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return DocumentRequestRules::rules($this->category());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return DocumentRequestRules::attributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return DocumentRequestRules::messages();
    }

    /**
     * Total lampiran per dokumen maksimal 10 MB.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => DocumentRequestRules::checkLampiranTotal($validator, (array) $this->file('proses', []))];
    }

    public function category(): ?DocumentCategory
    {
        $id = $this->input('kategori');

        return is_numeric($id) ? $this->resolvedCategory ??= DocumentCategory::find($id) : null;
    }
}
