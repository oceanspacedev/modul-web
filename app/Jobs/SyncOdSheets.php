<?php

namespace App\Jobs;

use App\Services\OdSheetSync;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kirim perubahan Daily Report ke spreadsheet Weekly Report. Unik sampai mulai diproses: banyak simpan beruntun
 * cukup satu kiriman, dan perubahan yang datang saat kiriman berjalan memicu kiriman berikutnya.
 */
class SyncOdSheets implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function handle(OdSheetSync $sync): void
    {
        $sync->run();
    }
}
