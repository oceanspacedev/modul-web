<?php

namespace App\Jobs;

use App\Imports\UserImport;
use App\Notifications\BackgroundTaskFinished;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import Excel karyawan dari file yang sudah diunggah ke disk "local".
 */
class ProcessUserImport extends BackgroundTask
{
    public function __construct(int $userId, public string $path)
    {
        parent::__construct($userId);
    }

    public function kind(): string
    {
        return 'user-import';
    }

    protected function failureTitle(): string
    {
        return 'Import user gagal';
    }

    protected function process(): BackgroundTaskFinished
    {
        $import = new UserImport;
        Excel::import($import, $this->path, 'local');

        $errors = $import->errors();
        $message = "Import selesai. Berhasil: {$import->successCount()} user.";

        if ($errors !== []) {
            $message .= ' Gagal: '.count($errors).' baris.';
        }

        return new BackgroundTaskFinished(
            $this->kind(),
            $errors === [] ? BackgroundTaskFinished::STATUS_SUCCESS : BackgroundTaskFinished::STATUS_PARTIAL,
            'Import user selesai',
            $message,
            $errors,
        );
    }

    protected function cleanup(): void
    {
        Storage::disk('local')->delete($this->path);
    }
}
