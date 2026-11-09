<?php

namespace App\Console\Commands;

use App\Jobs\GenerateExportFile;
use App\Jobs\ProcessDndSync;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;

/**
 * Membersihkan sisa proses di belakang layar: file export lama, file upload/snapshot DnD yang tertinggal,
 * dan notifikasi yang sudah lama dibaca.
 */
class PruneNotificationsCommand extends Command
{
    public const READ_NOTIFICATION_TTL_DAYS = 90;

    protected $signature = 'notifications:prune';

    protected $description = 'Hapus file export kedaluwarsa, file sementara import, dan notifikasi lama yang sudah dibaca';

    public function handle(): int
    {
        $disk = Storage::disk('local');

        $exportCutoff = now()->subDays(GenerateExportFile::EXPORT_TTL_DAYS)->getTimestamp();
        $exports = 0;
        foreach ($disk->directories(GenerateExportFile::DIRECTORY) as $directory) {
            $files = $disk->files($directory);
            if ($files === [] || $disk->lastModified($files[0]) < $exportCutoff) {
                $disk->deleteDirectory($directory);
                $exports++;
            }
        }

        // Upload import dan snapshot DnD normalnya dihapus job-nya; yang tersisa lebih dari sehari berarti yatim.
        $tempCutoff = now()->subDay()->getTimestamp();
        $temp = 0;
        foreach ([...$disk->files('imports'), ...$disk->files(ProcessDndSync::DIRECTORY)] as $file) {
            if ($disk->lastModified($file) < $tempCutoff) {
                $disk->delete($file);
                $temp++;
            }
        }

        $notifications = DatabaseNotification::whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays(self::READ_NOTIFICATION_TTL_DAYS))
            ->delete();

        $this->info("Dihapus: {$exports} file export, {$temp} file sementara, {$notifications} notifikasi lama.");

        return self::SUCCESS;
    }
}
