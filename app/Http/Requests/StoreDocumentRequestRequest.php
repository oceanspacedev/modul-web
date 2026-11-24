<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\OdDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Permintaan Dokumen (publik atau login): data pemohon, kategori dokumen, 1–10 item, dan jadwal observasi bila
 * kategorinya butuh observasi.
 */
class StoreDocumentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'whatsapp' => preg_replace('/[\s\-().]/', '', (string) $this->input('whatsapp')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);

        // Jadwal observasi hanya diisi pada kategori yang butuh observasi; nilai lama dari langkah yang tersembunyi
        // jangan membuat pengajuan ditolak tanpa pesan yang terlihat (DocumentRequestService juga mengabaikannya).
        if ($this->category()?->needs_observation) {
            return;
        }

        $this->merge(['obs_tanggal_1' => null, 'obs_tanggal_2' => null, 'obs_catatan' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $needsObservation = fn () => (bool) $this->category()?->needs_observation;

        return [
            'kategori' => ['required', 'integer', Rule::exists('document_categories', 'id')->where('is_active', true)],
            'nama' => ['required', 'string', 'max:100'],
            'divisi' => ['required', 'string', 'max:120'],
            'departemen' => ['required', Rule::in(DocumentRequest::DEPARTMENTS)],
            'jabatan' => ['required', Rule::in(DocumentRequest::JABATAN)],
            'atasan' => ['nullable', 'string', 'max:100'],
            'whatsapp' => ['required', 'regex:/^\+?\d{9,15}$/'],
            'email' => ['required', 'email', 'max:150'],
            'proses' => ['required', 'array', 'min:1', 'max:'.DocumentRequest::MAX_PROCESSES],
            'proses.*.judul' => ['required', 'string', 'max:200'],
            'proses.*.tujuan' => ['required', 'string', 'min:10', 'max:2000'],
            'proses.*.prioritas' => ['required', Rule::in(OdDocument::PRIORITAS)],
            'proses.*.sistem' => ['nullable', 'string', 'max:200'],
            'obs_tanggal_1' => [Rule::requiredIf($needsObservation), 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'obs_tanggal_2' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'obs_catatan' => ['nullable', 'string', 'max:300'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kategori' => 'kategori dokumen',
            'nama' => 'nama',
            'whatsapp' => 'nomor WhatsApp',
            'proses' => 'proses',
            'proses.*.judul' => 'judul',
            'proses.*.tujuan' => 'tujuan',
            'proses.*.prioritas' => 'prioritas',
            'proses.*.sistem' => 'sistem/alat',
            'obs_tanggal_1' => 'tanggal observasi pilihan 1',
            'obs_tanggal_2' => 'tanggal observasi pilihan 2',
            'obs_catatan' => 'catatan observasi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp.regex' => 'Nomor WhatsApp harus 9–15 digit.',
            'obs_tanggal_1.after_or_equal' => 'Tanggal observasi tidak boleh di masa lalu.',
            'obs_tanggal_2.after_or_equal' => 'Tanggal observasi tidak boleh di masa lalu.',
        ];
    }

    public function category(): ?DocumentCategory
    {
        $id = $this->input('kategori');

        return is_numeric($id) ? DocumentCategory::find($id) : null;
    }
}
