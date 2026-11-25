<?php

namespace App\Services\Helpdesk;

use App\Models\Area;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\OdDocument;
use App\Models\OdDocumentAttachment;
use App\Support\DocumentRequestRules;

/**
 * Definisi form satu Kategori Dokumen untuk helpdesk (kontrak integrasi §3.2). Batas dan pilihannya diambil dari
 * DocumentRequestRules / konstanta model yang sama dengan validasi, jadi form helpdesk tidak bisa berbeda dari form web.
 * Nama dan nomor WhatsApp pemohon diambil dari identitas pelapor helpdesk, bukan isian form.
 */
class FormSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function for(DocumentCategory $category): array
    {
        return [
            'key' => $category->code,
            'title' => $category->label(),
            'description' => (string) ($category->description ?? ''),
            'downloads' => self::downloads($category),
            'fields' => [
                self::field('email', 'email', 'Email', true, ['prefill' => 'reporter.email', 'rules' => ['max' => DocumentRequestRules::EMAIL_MAX]]),
                self::field('divisi', 'text', 'Divisi', true, ['prefill' => 'reporter.unit', 'rules' => ['max' => DocumentRequestRules::DIVISI_MAX]]),
                self::field('departemen', 'select', 'Departemen', true, ['prefill' => 'reporter.unit', 'options' => self::options(DocumentRequest::DEPARTMENTS)]),
                self::field('jabatan', 'select', 'Jabatan', true, ['prefill' => 'reporter.job_title', 'options' => self::options(DocumentRequest::JABATAN)]),
                self::field('atasan', 'text', 'Atasan langsung', false, ['rules' => ['max' => DocumentRequestRules::ATASAN_MAX]]),
                self::field('badan_usaha', 'select', 'Badan usaha', true, [
                    'prefill' => 'reporter.business_entity',
                    'options' => self::options(Area::orderBy('name')->pluck('name')->all()),
                ]),
                [
                    ...self::field('proses', 'repeater', 'Dokumen yang diajukan', true, [
                        'help' => 'Satu baris untuk setiap dokumen, maksimal '.DocumentRequest::MAX_PROCESSES.' dokumen.',
                    ]),
                    'min_items' => 1,
                    'max_items' => DocumentRequest::MAX_PROCESSES,
                    'fields' => self::processFields($category),
                ],
                ...($category->needs_observation ? self::observationFields() : []),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function processFields(DocumentCategory $category): array
    {
        $maxKb = OdDocumentAttachment::MAX_KB;
        $fields = [
            self::field('judul', 'text', 'Judul dokumen', true, ['rules' => ['max' => DocumentRequestRules::JUDUL_MAX]]),
            self::field('prioritas', 'radio', 'Prioritas', true, ['options' => self::options(OdDocument::PRIORITAS)]),
        ];

        if ($category->hasTemplate()) {
            return [
                ...$fields,
                self::field('jenis_pengajuan', 'radio', 'Keterangan', true, ['options' => self::options(OdDocument::JENIS_PENGAJUAN)]),
                self::field('template', 'file', 'Template terisi', true, [
                    'help' => 'Unduh template, isi, lalu unggah kembali.',
                    'rules' => ['accept' => OdDocumentAttachment::TEMPLATE_EXTENSIONS, 'max_kb' => $maxKb],
                ]),
            ];
        }

        return [
            ...$fields,
            self::field('tujuan', 'textarea', 'Uraian', true, [
                'help' => 'Jelaskan proses yang perlu didokumentasikan dan alasannya.',
                'rules' => ['min' => DocumentRequestRules::TUJUAN_MIN, 'max' => DocumentRequestRules::TUJUAN_MAX],
            ]),
            self::field('sistem', 'text', 'Sistem/alat yang dipakai', false, ['rules' => ['max' => DocumentRequestRules::SISTEM_MAX]]),
            self::field('lampiran', 'files', 'Lampiran pendukung', false, [
                'help' => 'Word, PDF, Excel, atau Visio.',
                'rules' => [
                    'accept' => OdDocumentAttachment::LAMPIRAN_EXTENSIONS,
                    'max_kb' => $maxKb,
                    'max_files' => OdDocumentAttachment::MAX_FILES,
                    'max_total_kb' => $maxKb,
                ],
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function observationFields(): array
    {
        return [
            self::field('obs_info', 'info', 'Jadwal observasi', false, ['help' => 'Tim OD akan observasi langsung. Pilih tanggal yang memungkinkan.']),
            self::field('obs_tanggal_1', 'date', 'Tanggal observasi pilihan 1', true, ['rules' => ['min_date' => 'today']]),
            self::field('obs_tanggal_2', 'date', 'Tanggal observasi pilihan 2', false, ['rules' => ['min_date' => 'today']]),
            self::field('obs_catatan', 'textarea', 'Catatan observasi', false, ['rules' => ['max' => DocumentRequestRules::OBS_CATATAN_MAX]]),
        ];
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private static function downloads(DocumentCategory $category): array
    {
        $downloads = [];
        if ($category->hasExample()) {
            $downloads[] = ['label' => "Contoh {$category->code}", 'url' => $category->exampleUrl()];
        }
        if ($category->hasTemplate()) {
            $downloads[] = ['label' => 'Template '.($category->template_name ?: $category->code), 'url' => route('document-request.template', $category)];
        }

        return $downloads;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function field(string $key, string $type, string $label, bool $required, array $extra = []): array
    {
        return ['key' => $key, 'type' => $type, 'label' => $label, 'required' => $required, ...$extra];
    }

    /**
     * @param  array<int, string>  $values
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $values): array
    {
        return array_values(array_map(fn (string $value) => ['value' => $value, 'label' => $value], $values));
    }
}
