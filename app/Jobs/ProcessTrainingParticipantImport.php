<?php

namespace App\Jobs;

use App\Imports\FirstSheetOnly;
use App\Imports\TrainingParticipantImport;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Notifications\BackgroundTaskFinished;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import peserta pelatihan dari file Excel/CSV yang sudah diunggah ke disk "local", sekaligus memutakhirkan data
 * user yang cocok di master User. Baris diproses satu per satu: baris yang ditolak dilaporkan, baris lain tetap masuk.
 */
class ProcessTrainingParticipantImport extends BackgroundTask
{
    public function __construct(int $userId, public int $trainingId, public string $path, public bool $sendInvitations = false)
    {
        parent::__construct($userId);
    }

    public function kind(): string
    {
        return 'training-participant-import';
    }

    public function uniqueId(): string
    {
        return parent::uniqueId().':'.$this->trainingId;
    }

    protected function failureTitle(): string
    {
        return 'Import peserta gagal';
    }

    protected function process(): BackgroundTaskFinished
    {
        $training = Training::findOrFail($this->trainingId);
        $import = new TrainingParticipantImport($training, User::findOrFail($this->userId));

        Excel::import(new FirstSheetOnly($import), $this->path, 'local');

        $invited = 0;
        if ($this->sendInvitations) {
            TrainingParticipant::whereKey($import->addedParticipantIds())->each(function (TrainingParticipant $participant) use (&$invited): void {
                $participant->update(['wa_status' => 'Dalam antrean']);
                SendTrainingInvitation::dispatch($participant);
                $invited++;
            });
        }

        $errors = $import->errors();
        $message = "Peserta \"{$training->title}\": {$import->addedCount()} ditambahkan, {$import->existingCount()} sudah terdaftar, "
            ."{$import->updatedUserCount()} data user diperbarui.";
        if ($invited > 0) {
            $message .= " Undangan WhatsApp untuk {$invited} peserta baru masuk antrean.";
        }
        if ($errors !== []) {
            $message .= ' Ditolak: '.count($errors).' baris.';
        }
        if ($import->notes() !== []) {
            $message .= ' Catatan: '.count($import->notes()).' baris.';
        }

        return new BackgroundTaskFinished(
            $this->kind(),
            $errors === [] && $import->notes() === [] ? BackgroundTaskFinished::STATUS_SUCCESS : BackgroundTaskFinished::STATUS_PARTIAL,
            'Import peserta selesai',
            $message,
            [...$errors, ...$import->notes()],
        );
    }

    protected function cleanup(): void
    {
        Storage::disk('local')->delete($this->path);
    }
}
