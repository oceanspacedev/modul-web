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
    /** Jumlah dokumen per pengajuan lewat helpdesk (form web modul tetap boleh sampai DocumentRequest::MAX_PROCESSES). */
    public const MAX_DOCUMENTS = 1;

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
            'steps' => self::steps($category),
            'fields' => [
                ...self::stepFields('dokumen', 'detail', [[
                    // Lewat helpdesk satu pengajuan = satu dokumen (max_items 1: helpdesk menampilkannya tanpa kartu baris).
                    ...self::field('proses', 'repeater', 'Dokumen yang diajukan', true),
                    'min_items' => 1,
                    'max_items' => self::MAX_DOCUMENTS,
                    'fields' => self::processFields($category),
                ]]),
                // Langkah pemohon: sama untuk semua kategori (kontrak §3.2 v1.1), urutannya mengikuti form web.
                ...self::stepFields('pemohon', 'identitas', [
                    // Karyawan yang dikenal helpdesk: terisi otomatis; pelapor baru mengisinya sendiri.
                    self::field('nama', 'text', 'Nama lengkap', true, ['prefill' => 'reporter.name', 'rules' => ['max' => DocumentRequestRules::NAMA_MAX]]),
                    self::field('divisi', 'text', 'Divisi', true, [
                        'prefill' => 'reporter.unit',
                        'placeholder' => 'Contoh: General Affair Aset',
                        'rules' => ['max' => DocumentRequestRules::DIVISI_MAX],
                    ]),
                    self::field('departemen', 'select', 'Departemen', true, ['prefill' => 'reporter.unit', 'options' => self::options(DocumentRequest::DEPARTMENTS)]),
                    self::field('jabatan', 'select', 'Jabatan', true, ['prefill' => 'reporter.job_level', 'options' => self::options(DocumentRequest::JABATAN)]),
                    self::field('badan_usaha', 'select', 'Badan usaha', true, [
                        'prefill' => 'ticket.business_entity',
                        'options' => self::options(Area::orderBy('name')->pluck('name')->all()),
                    ]),
                    self::field('atasan', 'text', 'Atasan langsung', false, ['rules' => ['max' => DocumentRequestRules::ATASAN_MAX]]),
                ]),
                ...self::stepFields('pemohon', 'kontak', [
                    // Helpdesk menyembunyikan field ini bila sudah punya nomor pelapor; akun tanpa nomor mengisinya di sini.
                    self::field('whatsapp', 'phone', 'Nomor WhatsApp', true, [
                        'prefill' => 'reporter.phone',
                        'placeholder' => 'Contoh: 081234567890',
                        'help' => 'Kabar pengajuan dikirim ke nomor ini.',
                    ]),
                    self::field('email', 'email', 'Email', true, ['prefill' => 'reporter.email', 'rules' => ['max' => DocumentRequestRules::EMAIL_MAX]]),
                ]),
                ...($category->needs_observation ? self::stepFields('observasi', 'jadwal', self::observationFields()) : []),
            ],
        ];
    }

    /**
     * Langkah wizard untuk helpdesk: Dokumen (pilih kategori lebih dulu) → Data pemohon → Jadwal observasi.
     * Langkah observasi tetap dideklarasikan untuk semua kategori; helpdesk melewatinya bila tidak ada field-nya.
     *
     * @return list<array<string, mixed>>
     */
    private static function steps(DocumentCategory $category): array
    {
        return [
            ['key' => 'dokumen', 'title' => 'Dokumen', 'category_picker' => true, 'sections' => [
                ['key' => 'detail', 'title' => 'Detail dokumen', 'help' => ($category->hasTemplate() ? 'Isi lengkapnya ada di template. ' : '')
                    .'Satu pengajuan untuk satu dokumen. Butuh dokumen lain? Kirim pengajuan baru.'],
            ]],
            ['key' => 'pemohon', 'title' => 'Data pemohon', 'sections' => [
                ['key' => 'identitas', 'title' => 'Identitas', 'help' => 'Isi sesuai data karyawan.'],
                ['key' => 'kontak', 'title' => 'Kontak', 'help' => 'Kami menghubungi Anda lewat kontak ini.'],
            ]],
            ['key' => 'observasi', 'title' => 'Jadwal observasi', 'sections' => [
                ['key' => 'jadwal', 'title' => 'Jadwal observasi', 'help' => 'Tim OD akan mengamati langsung proses kerja. Jadwal final dikonfirmasi lewat WhatsApp atau email.'],
            ]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private static function stepFields(string $step, string $section, array $fields): array
    {
        return array_map(fn (array $field) => [...$field, 'step' => $step, 'section' => $section], $fields);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function processFields(DocumentCategory $category): array
    {
        $maxKb = OdDocumentAttachment::MAX_KB;
        $fields = [
            self::field('judul', 'text', 'Judul dokumen', true, [
                'placeholder' => $category->hasTemplate() ? 'Contoh: Admin Gudang' : 'Contoh: Penerimaan barang di gudang',
                'rules' => ['max' => DocumentRequestRules::JUDUL_MAX],
            ]),
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
            self::field('sistem', 'text', 'Sistem/alat yang dipakai', false, [
                'placeholder' => 'Contoh: Accurate, Excel',
                'rules' => ['max' => DocumentRequestRules::SISTEM_MAX],
            ]),
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
            self::field('obs_tanggal_1', 'date', 'Tanggal observasi pilihan 1', true, ['rules' => ['min_date' => 'today']]),
            self::field('obs_tanggal_2', 'date', 'Tanggal observasi pilihan 2', false, ['rules' => ['min_date' => 'today']]),
            self::field('obs_catatan', 'textarea', 'Catatan observasi', false, [
                'placeholder' => 'Contoh: pagi 09.00–11.00 WIB',
                'suggestions' => ['Pagi', 'Siang', 'Di lokasi kerja'],
                'rules' => ['max' => DocumentRequestRules::OBS_CATATAN_MAX],
            ]),
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
            $downloads[] = ['label' => "Template {$category->code}", 'url' => route('document-request.template', $category)];
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
