<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dokumen dari Permintaan Dokumen tidak lagi mengikuti tahapan kategori, tapi tiga status tetap yang juga dilihat
 * pemohon: Pengajuan diterima → On Progress → Selesai. Dokumen OD lain tetap memakai tahapan kategorinya.
 * Tahap pertama kategori menjadi Pengajuan diterima, tahap terakhir (atau status selesai) menjadi Selesai, sisanya
 * On Progress. Timeline pengajuan dipetakan dengan cara yang sama.
 */
return new class extends Migration
{
    private const RECEIVED = 'Pengajuan diterima';

    private const PROGRESS = 'On Progress';

    private const DONE = 'Selesai';

    public function up(): void
    {
        $stages = $this->categoryStages();
        $done = DB::table('od_document_statuses')->where('type', 'done')->pluck('id')->all();

        DB::table('od_documents')->whereNotNull('document_request_id')->orderBy('id')
            ->select(['id', 'jenis', 'stage', 'od_document_status_id'])
            ->chunkById(500, function (Collection $documents) use ($stages, $done) {
                foreach ($documents as $document) {
                    $names = $stages->get($document->jenis, []);
                    $stage = in_array($document->od_document_status_id, $done) ? self::DONE : $this->map($document->stage, $names);
                    DB::table('od_documents')->where('id', $document->id)->update(['stage' => $stage]);
                }
            });

        $categories = DB::table('document_requests')->join('document_categories', 'document_categories.id', '=', 'document_requests.document_category_id')
            ->pluck('document_categories.code', 'document_requests.id');

        DB::table('document_request_histories')->orderBy('id')->select(['id', 'document_request_id', 'status'])
            ->chunkById(500, function (Collection $histories) use ($stages, $categories) {
                foreach ($histories as $history) {
                    $names = $stages->get($categories->get($history->document_request_id), []);
                    if (in_array($history->status, $names, true)) {
                        DB::table('document_request_histories')->where('id', $history->id)->update(['status' => $this->map($history->status, $names)]);
                    }
                }
            });
    }

    public function down(): void
    {
        $stages = $this->categoryStages();
        $reverse = fn (?string $stage, array $names) => match ($stage) {
            self::RECEIVED => $names[0] ?? $stage,
            self::PROGRESS => $names[1] ?? $names[0] ?? $stage,
            self::DONE => end($names) ?: $stage,
            default => $stage,
        };

        DB::table('od_documents')->whereNotNull('document_request_id')->orderBy('id')->select(['id', 'jenis', 'stage'])
            ->chunkById(500, function (Collection $documents) use ($stages, $reverse) {
                foreach ($documents as $document) {
                    DB::table('od_documents')->where('id', $document->id)->update(['stage' => $reverse($document->stage, $stages->get($document->jenis, []))]);
                }
            });

        $categories = DB::table('document_requests')->join('document_categories', 'document_categories.id', '=', 'document_requests.document_category_id')
            ->pluck('document_categories.code', 'document_requests.id');

        DB::table('document_request_histories')->orderBy('id')->select(['id', 'document_request_id', 'status'])
            ->chunkById(500, function (Collection $histories) use ($stages, $categories, $reverse) {
                foreach ($histories as $history) {
                    $names = $stages->get($categories->get($history->document_request_id), []);
                    DB::table('document_request_histories')->where('id', $history->id)->update(['status' => $reverse($history->status, $names)]);
                }
            });
    }

    /** @param list<string> $names */
    private function map(?string $stage, array $names): string
    {
        if ($stage === null || $names === [] || $stage === $names[0]) {
            return self::RECEIVED;
        }

        return $stage === end($names) ? self::DONE : self::PROGRESS;
    }

    /**
     * Nama tahapan per kode kategori, berurutan.
     *
     * @return Collection<string, list<string>>
     */
    private function categoryStages(): Collection
    {
        return DB::table('document_category_stages')
            ->join('document_categories', 'document_categories.id', '=', 'document_category_stages.document_category_id')
            ->orderBy('document_category_stages.sort')->orderBy('document_category_stages.id')
            ->get(['document_categories.code', 'document_category_stages.name'])
            ->groupBy('code')
            ->map(fn (Collection $rows) => $rows->pluck('name')->all());
    }
};
