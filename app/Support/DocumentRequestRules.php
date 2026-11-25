<?php

namespace App\Support;

use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\OdDocument;
use App\Models\OdDocumentAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Aturan form Permintaan Dokumen, satu sumber untuk form web (StoreDocumentRequestRequest) dan API helpdesk (validasi
 * kiriman dan definisi form). Batas panjang dan file ada di konstanta di sini supaya keduanya tidak bisa berbeda.
 */
class DocumentRequestRules
{
    public const NAMA_MAX = 100;

    public const DIVISI_MAX = 120;

    public const ATASAN_MAX = 100;

    public const EMAIL_MAX = 150;

    public const BADAN_USAHA_MAX = 120;

    public const JUDUL_MAX = 200;

    public const TUJUAN_MIN = 10;

    public const TUJUAN_MAX = 2000;

    public const SISTEM_MAX = 200;

    public const OBS_CATATAN_MAX = 300;

    public const IDEMPOTENCY_KEY_MAX = 64;

    public const WHATSAPP_PATTERN = '/^\+?\d{9,15}$/';

    /**
     * Rapikan isian sebelum divalidasi: nomor WhatsApp tanpa spasi/tanda baca, email huruf kecil, dan jadwal observasi
     * dikosongkan bila kategorinya tidak butuh observasi (nilai lama dari langkah yang tersembunyi tidak boleh membuat
     * pengajuan ditolak tanpa pesan yang terlihat; DocumentRequestService juga mengabaikannya).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input, ?DocumentCategory $category): array
    {
        $input['whatsapp'] = preg_replace('/[\s\-().]/', '', (string) ($input['whatsapp'] ?? ''));
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));

        if (! $category?->needs_observation) {
            $input = [...$input, 'obs_tanggal_1' => null, 'obs_tanggal_2' => null, 'obs_catatan' => null];
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(?DocumentCategory $category): array
    {
        $needsObservation = (bool) $category?->needs_observation;
        $template = (bool) $category?->hasTemplate();
        $maxKb = OdDocumentAttachment::MAX_KB;

        return [
            'kategori' => ['required', 'integer', Rule::exists('document_categories', 'id')->where('is_active', true)],
            'nama' => ['required', 'string', 'max:'.self::NAMA_MAX],
            'divisi' => ['required', 'string', 'max:'.self::DIVISI_MAX],
            'departemen' => ['required', Rule::in(DocumentRequest::DEPARTMENTS)],
            'jabatan' => ['required', Rule::in(DocumentRequest::JABATAN)],
            'atasan' => ['nullable', 'string', 'max:'.self::ATASAN_MAX],
            'whatsapp' => ['required', 'regex:'.self::WHATSAPP_PATTERN],
            'email' => ['required', 'email', 'max:'.self::EMAIL_MAX],
            'badan_usaha' => ['required', 'string', 'max:'.self::BADAN_USAHA_MAX, Rule::exists('areas', 'name')],
            'proses' => ['required', 'array', 'min:1', 'max:'.DocumentRequest::MAX_PROCESSES],
            'proses.*.judul' => ['required', 'string', 'max:'.self::JUDUL_MAX],
            'proses.*.prioritas' => ['required', Rule::in(OdDocument::PRIORITAS)],
            ...($template ? [
                'proses.*.jenis_pengajuan' => ['required', Rule::in(OdDocument::JENIS_PENGAJUAN)],
                'proses.*.template' => ['required', 'file', 'extensions:'.implode(',', OdDocumentAttachment::TEMPLATE_EXTENSIONS), "max:{$maxKb}"],
            ] : [
                'proses.*.tujuan' => ['required', 'string', 'min:'.self::TUJUAN_MIN, 'max:'.self::TUJUAN_MAX],
                'proses.*.sistem' => ['nullable', 'string', 'max:'.self::SISTEM_MAX],
                'proses.*.lampiran' => ['nullable', 'array', 'max:'.OdDocumentAttachment::MAX_FILES],
                'proses.*.lampiran.*' => ['file', 'extensions:'.implode(',', OdDocumentAttachment::LAMPIRAN_EXTENSIONS), "max:{$maxKb}"],
            ]),
            'obs_tanggal_1' => [Rule::requiredIf($needsObservation), 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'obs_tanggal_2' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'obs_catatan' => ['nullable', 'string', 'max:'.self::OBS_CATATAN_MAX],
            'idempotency_key' => ['nullable', 'string', 'max:'.self::IDEMPOTENCY_KEY_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'kategori' => 'kategori dokumen',
            'nama' => 'nama',
            'divisi' => 'divisi',
            'departemen' => 'departemen',
            'jabatan' => 'jabatan',
            'atasan' => 'atasan langsung',
            'whatsapp' => 'nomor WhatsApp',
            'email' => 'email',
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
    public static function messages(): array
    {
        $maxMb = self::maxMb();

        return [
            'whatsapp.regex' => 'Nomor WhatsApp harus 9–15 digit.',
            'proses.*.lampiran.*.extensions' => 'Lampiran harus berupa Word, PDF, Excel, atau Visio.',
            'proses.*.lampiran.max' => 'Lampiran maksimal '.OdDocumentAttachment::MAX_FILES.' file per dokumen.',
            'proses.*.lampiran.*.max' => "Ukuran lampiran maksimal {$maxMb} MB per file.",
            'proses.*.template.extensions' => 'Template harus berupa Word, Excel, atau PDF.',
            'proses.*.template.max' => "Ukuran template maksimal {$maxMb} MB.",
            'obs_tanggal_1.after_or_equal' => 'Tanggal observasi tidak boleh di masa lalu.',
            'obs_tanggal_2.after_or_equal' => 'Tanggal observasi tidak boleh di masa lalu.',
        ];
    }

    /**
     * Total lampiran per dokumen maksimal OdDocumentAttachment::MAX_KB.
     *
     * @param  array<array-key, mixed>  $processes  isian proses (beserta file-nya)
     */
    public static function checkLampiranTotal(Validator $validator, array $processes): void
    {
        foreach ($processes as $index => $process) {
            $files = is_array($process) ? (array) ($process['lampiran'] ?? []) : [];
            $total = collect($files)->sum(fn ($file) => $file instanceof UploadedFile ? $file->getSize() : 0);
            if ($total > OdDocumentAttachment::MAX_KB * 1024) {
                $validator->errors()->add("proses.{$index}.lampiran", 'Total lampiran satu dokumen maksimal '.self::maxMb().' MB.');
            }
        }
    }

    private static function maxMb(): int
    {
        return intdiv(OdDocumentAttachment::MAX_KB, 1024);
    }
}
