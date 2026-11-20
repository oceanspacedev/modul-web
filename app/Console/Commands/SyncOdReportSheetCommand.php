<?php

namespace App\Console\Commands;

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
            $this->warn('Sinkron Google Sheets belum diatur (atur di Pengaturan OD), dilewati.');

            return self::SUCCESS;
        }

        try {
            do {
                $result = $sync->run();
                $this->info("{$result['written']} ditulis, {$result['failed']} gagal, sisa {$result['remaining']}.");
            } while ($result['remaining'] > 0 && $result['written'] > 0);
        } catch (Throwable $e) {
            report($e);
            $this->error('Sinkron gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
