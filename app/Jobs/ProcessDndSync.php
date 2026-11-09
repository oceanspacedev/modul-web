<?php

namespace App\Jobs;

use App\Notifications\BackgroundTaskFinished;
use App\Services\DndUserSyncService;
use Illuminate\Support\Facades\Storage;

/**
 * Menerapkan snapshot sinkronisasi DnD yang sudah ditinjau user di halaman preview.
 */
class ProcessDndSync extends BackgroundTask
{
    public const DIRECTORY = 'dnd-sync';

    public function __construct(int $userId, public string $token)
    {
        parent::__construct($userId);
    }

    public static function snapshotPath(string $token): string
    {
        return self::DIRECTORY.'/'.$token.'.json';
    }

    public function kind(): string
    {
        return 'dnd-sync';
    }

    protected function failureTitle(): string
    {
        return 'Sinkronisasi DnD gagal';
    }

    protected function process(): BackgroundTaskFinished
    {
        $content = Storage::disk('local')->get(self::snapshotPath($this->token));
        if ($content === null) {
            return $this->failure('Sesi sinkronisasi sudah kedaluwarsa, silakan ulangi.');
        }

        $result = app(DndUserSyncService::class)->apply(json_decode($content, true));

        // Snapshot hanya dihapus setelah berhasil, supaya proses yang gagal bisa diulang dari halaman preview.
        // Sisa snapshot yang tidak pernah diproses dibersihkan oleh notifications:prune.
        Storage::disk('local')->delete(self::snapshotPath($this->token));

        return new BackgroundTaskFinished(
            $this->kind(),
            BackgroundTaskFinished::STATUS_SUCCESS,
            'Sinkronisasi DnD selesai',
            "Sinkronisasi DnD selesai. Baru: {$result['created']}, diperbarui: {$result['updated']}, tidak berubah: {$result['unchanged']}.",
            $result['notices'],
        );
    }
}
