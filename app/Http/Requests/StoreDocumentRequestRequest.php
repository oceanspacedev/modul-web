<?php

namespace App\Http\Requests;

use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\OdDocument;
use App\Models\OdDocumentAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Form Permintaan Dokumen (publik atau login): data pemohon, kategori dokumen, 1–10 item, dan jadwal observasi bila
 * kategorinya butuh observasi. Item biasa berisi judul, uraian, lampiran opsional (Word, PDF, Excel, Visio), prioritas
 * dan sistem; item kategori bertemplate (mis. Jobdesk) berisi judul, keterangan baru/revisi, prioritas, dan template
 * yang sudah diisi.
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
        $template = (bool) $this->category()?->hasTemplate();
        $maxKb = OdDocumentAttachment::MAX_KB;

        return [
            'kategori' => ['required', 'integer', Rule::exists('document_categories', 'id')->where('is_active', true)],
            'nama' => ['required', 'string', 'max:100'],
            'divisi' => ['required', 'string', 'max:120'],
            'departemen' => ['required', Rule::in(DocumentRequest::DEPARTMENTS)],
            'jabatan' => ['required', Rule::in(DocumentRequest::JABATAN)],
            'atasan' => ['nullable', 'string', 'max:100'],
            'whatsapp' => ['required', 'regex:/^\+?\d{9,15}$/'],
            'email' => ['required', 'email', 'max:150'],
            'badan_usaha' => ['required', 'string', 'max:120', Rule::exists('areas', 'name')],
            'proses' => ['required', 'array', 'min:1', 'max:'.DocumentRequest::MAX_PROCESSES],
            'proses.*.judul' => ['required', 'string', 'max:200'],
            'proses.*.prioritas' => ['required', Rule::in(OdDocument::PRIORITAS)],
            ...($template ? [
                'proses.*.jenis_pengajuan' => ['required', Rule::in(OdDocument::JENIS_PENGAJUAN)],
                'proses.*.template' => ['required', 'file', 'extensions:'.implode(',', OdDocumentAttachment::TEMPLATE_EXTENSIONS), "max:{$maxKb}"],
            ] : [
                'proses.*.tujuan' => ['required', 'string', 'min:10', 'max:2000'],
                'proses.*.sistem' => ['nullable', 'string', 'max:200'],
                'proses.*.lampiran' => ['nullable', 'array', 'max:'.OdDocumentAttachment::MAX_FILES],
                'proses.*.lampiran.*' => ['file', 'extensions:'.implode(',', OdDocumentAttachment::LAMPIRAN_EXTENSIONS), "max:{$maxKb}"],
            ]),
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
            'proses.*.tujuan' => 'uraian',
            'proses.*.prioritas' => 'prioritas',
            'proses.*.sistem' => 'sistem/alat',
            'proses.*.lampiran' => 'lampiran',
            'proses.*.lampiran.*' => 'lampiran',
            'proses.*.template' => 'template terisi',
            'proses.*.jenis_pengajuan' => 'keterangan',
            'badan_usaha' => 'badan usaha',
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
            'proses.*.lampiran.*.extensions' => 'Lampiran harus berupa Word, PDF, Excel, atau Visio.',
            'proses.*.lampiran.max' => 'Lampiran maksimal '.OdDocumentAttachment::MAX_FILES.' file per dokumen.',
            'proses.*.lampiran.*.max' => 'Ukuran lampiran maksimal 10 MB per file.',
            'proses.*.template.extensions' => 'Template harus berupa Word, Excel, atau PDF.',
            'proses.*.template.max' => 'Ukuran template maksimal 10 MB.',
            'obs_tanggal_1.after_or_equal' => 'Tanggal observasi tidak boleh di masa lalu.',
            'obs_tanggal_2.after_or_equal' => 'Tanggal observasi tidak boleh di masa lalu.',
        ];
    }

    /**
     * Total lampiran per dokumen maksimal 10 MB.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->file('proses', []) as $index => $files) {
                $total = collect($files['lampiran'] ?? [])->sum(fn ($file) => $file instanceof UploadedFile ? $file->getSize() : 0);
                if ($total > OdDocumentAttachment::MAX_KB * 1024) {
                    $validator->errors()->add("proses.{$index}.lampiran", 'Total lampiran satu dokumen maksimal 10 MB.');
                }
            }
        }];
    }

    public function category(): ?DocumentCategory
    {
        $id = $this->input('kategori');

        return is_numeric($id) ? $this->resolvedCategory ??= DocumentCategory::find($id) : null;
    }
}
