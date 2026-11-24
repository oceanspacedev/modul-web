<?php

namespace App\Jobs;

use App\Services\OdSheetSync;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mengirim perubahan Daily Report ke spreadsheet Weekly Report. Job tanpa daftar hapus cukup satu di antrean: ia
 * membaca semua perubahan saat dijalankan. Bila perubahan lebih dari satu batch, job mengantre dirinya lagi —
 * termasuk bila spreadsheet menolak semua barisnya, dengan batas batch tanpa kemajuan.
 */
class SyncOdReportSheet implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 55;

    /** @var list<int> */
    public array $backoff = [30, 120];

    /**
     * Batch beruntun tanpa satu baris pun tertulis (Web App menolak semuanya) sebelum antrean dihentikan.
     * Sisanya tetap tertunda dan dicoba lagi oleh job berikutnya atau jadwal od-report:sync-sheet.
     */
    public const MAX_ROUNDS_WITHOUT_PROGRESS = 3;

    /**
     * @param  list<string>  $delete  SYNC ID baris yang harus dikosongkan
     * @param  int  $stalled  batch beruntun yang sebelumnya ditolak tanpa kemajuan
     */
    public function __construct(public array $delete = [], public int $stalled = 0) {}

    public function uniqueId(): string
    {
        return $this->delete ? 'delete:'.implode(',', $this->delete) : 'pending';
    }

    public function handle(OdSheetSync $sync): void
    {
        if (! $sync->enabled()) {
            return;
        }

        $result = $sync->run($this->delete);

        if ($result['remaining'] === 0) {
            return;
        }

        // Sisa antrean dikirim dengan job berikutnya; kunci unik sudah dilepas sebelum handle() berjalan,
        // jadi job boleh mengantre dirinya sendiri tanpa mengunci dirinya.
        $stalled = $result['written'] > 0 ? 0 : $this->stalled + 1;
        if ($stalled < self::MAX_ROUNDS_WITHOUT_PROGRESS) {
            self::dispatch([], $stalled);
        }
    }
}
