<?php

namespace App\Console\Commands;

use App\Services\DndUserSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncDndUsersCommand extends Command
{
    protected $signature = 'dnd:sync-users';

    protected $description = 'Sinkronisasi karyawan, area, divisi, posisi, dan jabatan dari DnD';

    public function handle(DndUserSyncService $sync): int
    {
        if (! DndUserSyncService::isConfigured()) {
            $this->warn('Koneksi DnD belum diatur (DND_API_URL & DND_API_TOKEN), sinkronisasi dilewati.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->apply($sync->fetch());
        } catch (Throwable $e) {
            report($e);
            $this->error('Sinkronisasi DnD gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sinkronisasi DnD selesai. Baru: {$result['created']}, diperbarui: {$result['updated']}, tidak berubah: {$result['unchanged']}.");

        foreach ($result['notices'] as $notice) {
            $this->line("- {$notice}");
        }

        if ($result['notices'] !== []) {
            Log::info('Catatan sinkronisasi DnD', ['notices' => $result['notices']]);
        }

        return self::SUCCESS;
    }
}
