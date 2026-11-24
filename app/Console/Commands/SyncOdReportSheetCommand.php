<?php

namespace App\Console\Commands;

use App\Jobs\SyncOdReportSheet;
use App\Services\OdSheetSync;
use Illuminate\Console\Command;
use Throwable;

class SyncOdReportSheetCommand extends Command
{
    protected $signature = 'od-report:sync-sheet';

    protected $description = 'Kirim Daily Report OD yang belum tersinkron ke spreadsheet Weekly Report (Google Sheets)';

    public function handle(OdSheetSync $sync): int
    {
        if (! $sync->enabled()) {
            $this->warn('Sinkron Google Sheets belum diatur (atur di menu Pengaturan), dilewati.');

            return self::SUCCESS;
        }

        $failed = 0;
        $stalled = 0;

        try {
            do {
                $result = $sync->run();
                $this->info("{$result['written']} ditulis, {$result['failed']} gagal, sisa {$result['remaining']}.");
                $failed += $result['failed'];
                $stalled = $result['written'] > 0 ? 0 : $stalled + 1;
            } while ($result['remaining'] > 0 && $stalled < SyncOdReportSheet::MAX_ROUNDS_WITHOUT_PROGRESS);
        } catch (Throwable $e) {
            report($e);
            $this->error('Sinkron gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($result['remaining'] > 0) {
            $this->error("{$result['remaining']} pekerjaan masih ditolak spreadsheet, sinkron dihentikan setelah {$stalled} batch tanpa kemajuan.");

            return self::FAILURE;
        }

        // Baris yang ditolak tidak pernah tercatat di sheet, jadi perintah ini harus tampak gagal.
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
