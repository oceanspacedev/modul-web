<?php

namespace App\Jobs;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Sends one participant their training invitation over WhatsApp.
 *
 * The gateway call already retries at HTTP level and records failures in
 * "wa_status", so the job only retries on unexpected crashes. It is unique per
 * participant so the 15-minute reminder schedule cannot queue duplicates.
 */
#[Tries(3)]
#[Backoff(30, 120)]
#[Timeout(60)]
#[UniqueFor(900)]
#[DeleteWhenMissingModels]
class SendTrainingInvitation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public TrainingParticipant $participant) {}

    public function uniqueId(): string
    {
        return (string) $this->participant->id;
    }

    public function handle(): void
    {
        WhatsAppService::sendToParticipant($this->participant);
    }

    /**
     * Queue an invitation for every participant of a training and return how many were queued.
     */
    public static function dispatchForTraining(Training $training, bool $onlyUnnotified = false): int
    {
        $participants = $training->participants()
            ->when($onlyUnnotified, fn ($query) => $query->whereNull('wa_sent_at'))
            ->get();

        foreach ($participants as $participant) {
            $participant->update(['wa_status' => 'Dalam antrean']);
            static::dispatch($participant);
        }

        return $participants->count();
    }
}
