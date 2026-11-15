<?php

namespace App\Jobs;

use App\Models\Training;
use App\Models\TrainingMeetingEvent;
use App\Models\TrainingMeetingMonitor;
use App\Notifications\BackgroundTaskFinished;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Menarik jam masuk dan keluar peserta dari API platform meeting pelatihan (Google Meet, Microsoft Teams) ke
 * riwayat aktivitas meeting. Aman dijalankan berulang: kejadian yang sama tidak tercatat dua kali.
 */
class SyncMeetingAttendance extends BackgroundTask
{
    public const QUEUED_MESSAGE = 'Data kehadiran sedang ditarik di belakang layar. Hasilnya akan dikirim ke menu Notifikasi, WhatsApp, dan email Anda.';

    public function __construct(int $userId, public int $trainingId)
    {
        parent::__construct($userId);
    }

    public function kind(): string
    {
        return 'meeting-attendance-sync';
    }

    public function uniqueId(): string
    {
        return parent::uniqueId().':'.$this->trainingId;
    }

    protected function failureTitle(): string
    {
        return 'Penarikan kehadiran meeting gagal';
    }

    protected function process(): BackgroundTaskFinished
    {
        $training = Training::findOrFail($this->trainingId);
        $provider = $training->meetingProvider();
        $account = $training->hasMeetingMonitoring() ? $training->meetingAccount()->first() : null;
        $meetingCode = $training->meetingCode();
        if ($account === null || $meetingCode === null || ! $provider->supportsAttendanceSync()) {
            throw new RuntimeException('Pengaturan meeting pelatihan ini tidak lagi mendukung penarikan kehadiran.');
        }

        $events = $provider->fetchAttendance($account, $training->meeting_link, $meetingCode);
        if ($events === []) {
            return new BackgroundTaskFinished(
                $this->kind(),
                BackgroundTaskFinished::STATUS_PARTIAL,
                'Belum ada data kehadiran',
                "{$provider->label()} belum memiliki catatan kehadiran untuk rapat \"{$training->title}\". Coba lagi setelah rapat berakhir.",
            );
        }

        $new = DB::transaction(function () use ($training, $provider, $account, $meetingCode, $events): int {
            // Satu catatan penarikan per rapat; penarikan ulang memperbaruinya.
            $monitor = TrainingMeetingMonitor::query()
                ->where('training_id', $training->id)
                ->where('provider', $provider->key())
                ->where('meeting_code', $meetingCode)
                ->where('source', TrainingMeetingMonitor::SOURCE_IMPORT)
                ->lockForUpdate()
                ->first() ?? new TrainingMeetingMonitor([
                    'id' => (string) Str::uuid(),
                    'training_id' => $training->id,
                    'provider' => $provider->key(),
                    'meeting_code' => $meetingCode,
                    'source' => TrainingMeetingMonitor::SOURCE_IMPORT,
                ]);

            $times = collect($events)->pluck('observed_at');
            $now = now();
            $monitor->fill([
                'user_id' => $this->userId,
                'account_revision' => $training->meetingMonitorRevision($account),
                'started_at' => $times->min()->copy()->setTimezone(config('app.timezone')),
                'ended_at' => $times->max()->copy()->setTimezone(config('app.timezone')),
                'last_seen_at' => $now,
            ])->save();

            $before = $monitor->events()->count();
            foreach (array_chunk($events, 200) as $batch) {
                TrainingMeetingEvent::upsert(array_map(fn (array $event): array => [
                    'monitor_id' => $monitor->getKey(),
                    'event_id' => Uuid::uuid5(Uuid::NAMESPACE_URL, $event['key'])->toString(),
                    'type' => $event['type'],
                    'observed_at' => Carbon::instance($event['observed_at'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s.v'),
                    'participant_key' => $event['participant_key'],
                    'name' => $event['name'],
                    'camera_on' => $event['camera_on'],
                ], $batch), ['monitor_id', 'event_id'], ['event_id']);
            }

            return $monitor->events()->count() - $before;
        });

        return new BackgroundTaskFinished(
            $this->kind(),
            BackgroundTaskFinished::STATUS_SUCCESS,
            'Kehadiran meeting ditarik',
            "Kehadiran {$provider->label()} untuk \"{$training->title}\": {$new} aktivitas baru dari ".count($events).' aktivitas tercatat.',
        );
    }
}
