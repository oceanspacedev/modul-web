<?php

namespace App\Jobs;

use App\Services\OdSheetSync;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mengirim perubahan Daily Report ke spreadsheet Weekly Report. Job tanpa daftar hapus cukup satu di antrean: ia
 * membaca semua perubahan saat dijalankan. Bila perubahan lebih dari satu batch, job mengantre dirinya lagi.
 */
class SyncOdReportSheet implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 55;

    /** @var list<int> */
    public array $backoff = [30, 120];

    /** @param list<string> $delete SYNC ID baris yang harus dikosongkan */
    public function __construct(public array $delete = []) {}

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

        if ($result['remaining'] > 0 && $result['written'] > 0) {
            self::dispatch();
        }
    }
}
