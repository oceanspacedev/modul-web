<?php

namespace App\Jobs;

use App\Notifications\BackgroundTaskFinished;
use App\Services\UserJsonImportService;
use Illuminate\Support\Facades\Storage;

/**
 * Import JSON karyawan (Talenta) dari file yang sudah diunggah ke disk "local".
 */
class ProcessUserJsonImport extends BackgroundTask
{
    public function __construct(int $userId, public string $path)
    {
        parent::__construct($userId);
    }

    public function kind(): string
    {
        return 'user-import-json';
    }

    protected function failureTitle(): string
    {
        return 'Import JSON gagal';
    }

    protected function process(): BackgroundTaskFinished
    {
        $content = Storage::disk('local')->get($this->path);
        if ($content === null) {
            return $this->failure('File yang diunggah tidak ditemukan. Silakan unggah ulang.');
        }

        $result = UserJsonImportService::importFromContent($content);

        $message = $result['message'];
        if ($result['created_count'] > 0) {
            $message .= " User baru: {$result['created_count']}.";
        }
        if ($result['error_count'] > 0) {
            $message .= " Gagal: {$result['error_count']} baris.";
        }

        $status = match (true) {
            ! $result['success'] => BackgroundTaskFinished::STATUS_FAILED,
            $result['error_count'] > 0 => BackgroundTaskFinished::STATUS_PARTIAL,
            default => BackgroundTaskFinished::STATUS_SUCCESS,
        };

        return new BackgroundTaskFinished(
            $this->kind(),
            $status,
            $result['success'] ? 'Import JSON selesai' : $this->failureTitle(),
            $message,
            [...$result['errors'], ...$result['notices']],
        );
    }

    protected function cleanup(): void
    {
        Storage::disk('local')->delete($this->path);
    }
}
