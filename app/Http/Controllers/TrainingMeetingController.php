<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMeetingAttendance;
use App\Models\Training;
use App\Models\TrainingMeetingChatMessage;
use App\Models\TrainingMeetingEvent;
use App\Models\TrainingMeetingMonitor;
use App\Support\Meetings\MeetingCameraReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Halaman aktivitas meeting sebuah pelatihan: pemantauan langsung, riwayat aktivitas, dan chat publik. Cara
 * bergabung dan format chat ditentukan MeetingProvider platform pelatihannya.
 */
class TrainingMeetingController extends Controller
{
    public function show(Request $request, Training $training): View
    {
        Gate::authorize('update', $training);

        [$currentMeeting, $meetingCode, $meetingOptions] = $this->selectMeeting($request, $training);
        $account = $meetingCode === $currentMeeting && $training->hasMeetingMonitoring()
            ? $training->meetingAccount()->first()
            : null;

        return view('training.meeting', [
            'title' => 'Aktivitas Meeting',
            'active' => 'training',
            'training' => $training,
            'provider' => $training->meetingProvider(),
            'meetingAccount' => $account,
            'currentMeeting' => $currentMeeting,
            'meetingCode' => $meetingCode,
            'meetingOptions' => $meetingOptions,
            ...$this->activityData(
                $request,
                $training,
                $meetingCode,
                $account === null ? null : $training->meetingMonitorRevision($account),
            ),
            'messages' => $this->chatMessages($training, $meetingCode),
        ]);
    }

    /** Daftar chat publik saja, dimuat ulang halaman monitoring setiap ada chat baru yang tersimpan. */
    public function chat(Request $request, Training $training): View
    {
        Gate::authorize('update', $training);

        [, $meetingCode] = $this->selectMeeting($request, $training);

        return view('training.detail.meeting-chat', [
            'messages' => $this->chatMessages($training, $meetingCode),
        ]);
    }

    public function activity(Request $request, Training $training): View
    {
        Gate::authorize('update', $training);

        [$currentMeeting, $meetingCode] = $this->selectMeeting($request, $training);
        $account = $meetingCode === $currentMeeting && $training->hasMeetingMonitoring()
            ? $training->meetingAccount()->first()
            : null;

        return view('training.detail.meeting-activity', $this->activityData(
            $request,
            $training,
            $meetingCode,
            $account === null ? null : $training->meetingMonitorRevision($account),
        ));
    }

    /**
     * Data untuk bergabung ke rapat sebagai pemantau. Isinya ditentukan platform (mis. tanda tangan SDK Zoom).
     */
    public function join(Training $training): JsonResponse
    {
        Gate::authorize('update', $training);

        $account = $training->hasLiveMeetingMonitoring() ? $training->meetingAccount()->first() : null;
        abort_if($account === null, 409, 'Aktifkan monitoring dan pilih akun penyelenggara di pengaturan pelatihan.');

        $meetingCode = $training->meetingCode();
        abort_if($meetingCode === null, 404);

        return response()->json([
            ...$training->meetingProvider()->joinPayload($account, $meetingCode),
            'provider' => $training->meetingPlatform(),
            'meetingCode' => $meetingCode,
            'accountRevision' => $training->meetingMonitorRevision($account),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Tarik jam masuk dan keluar peserta dari API platform (Google Meet, Teams). Berjalan di antrean; hasilnya
     * muncul di riwayat aktivitas dan dikabarkan lewat notifikasi.
     */
    public function syncAttendance(Request $request, Training $training): RedirectResponse
    {
        Gate::authorize('update', $training);

        abort_unless($training->hasMeetingMonitoring(), 409, 'Aktifkan monitoring dan pilih akun penyelenggara di pengaturan pelatihan.');
        abort_unless($training->meetingCode() !== null && $training->meetingProvider()->supportsAttendanceSync(), 404);

        SyncMeetingAttendance::dispatch($request->user()->getKey(), $training->getKey());

        return to_route('training.meeting.show', $training)->with('info', SyncMeetingAttendance::QUEUED_MESSAGE);
    }

    /**
     * Rekap kamera peserta menurut monitoring meeting, untuk diverifikasi di tabel rekap kamera halaman detail
     * pelatihan sebelum disimpan.
     */
    public function camera(Training $training): JsonResponse
    {
        Gate::authorize('update', $training);
        abort_unless($training->isOnline(), 404);

        return response()->json([
            'camera_participants' => MeetingCameraReport::forTraining($training),
        ]);
    }

    /** @return array{?string, string, list<string>} */
    private function selectMeeting(Request $request, Training $training): array
    {
        $current = $training->meetingCode();
        $history = TrainingMeetingMonitor::where('training_id', $training->id)
            ->select(['meeting_code', 'started_at as recorded_at'])
            ->unionAll(TrainingMeetingChatMessage::where('training_id', $training->id)
                ->select(['meeting_code', 'created_at as recorded_at']));
        $historicalCodes = DB::query()
            ->fromSub($history, 'meeting_history')
            ->groupBy('meeting_code')
            ->orderByRaw('MAX(recorded_at) DESC')
            ->orderBy('meeting_code')
            ->pluck('meeting_code')
            ->map(fn ($code) => (string) $code)
            ->all();
        $options = $current === null
            ? $historicalCodes
            : array_values(array_unique([$current, ...$historicalCodes]));
        $selected = $request->query('meeting') ?? ($options[0] ?? null);
        abort_unless(is_string($selected) && in_array($selected, $options, true), 404);

        return [$current, $selected, $options];
    }

    private function chatMessages(Training $training, string $meetingCode): LengthAwarePaginator
    {
        return TrainingMeetingChatMessage::query()
            ->where('training_id', $training->id)
            ->where('meeting_code', $meetingCode)
            ->orderBy('id')
            ->paginate(30, pageName: 'chat')
            ->withPath(route('training.meeting.show', $training))
            ->withQueryString()
            ->appends('meeting', $meetingCode)
            ->fragment('meeting-chat');
    }

    private function activityData(Request $request, Training $training, string $meetingCode, ?string $monitorRevision): array
    {
        $monitors = TrainingMeetingMonitor::query()
            ->where('training_id', $training->id)
            ->where('meeting_code', $meetingCode);
        $participantName = $request->string('nama', strict: true)->trim()->limit(120, '')->toString();
        $events = TrainingMeetingEvent::query()
            ->whereIn('monitor_id', (clone $monitors)->select('id'))
            // Nama dicari apa adanya: % dan _ pada kata kunci bukan wildcard.
            ->when($participantName !== '', fn ($query) => $query->whereRaw(
                "name like ? escape '!'",
                ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $participantName).'%'],
            ))
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->paginate(50, pageName: 'aktivitas')
            ->withPath(route('training.meeting.show', $training))
            ->withQueryString()
            ->appends('meeting', $meetingCode)
            ->fragment('meeting-log');

        return [
            'participantName' => $participantName,
            'events' => $events,
            'monitors' => $monitors->orderByDesc('started_at')->limit(10)->get(),
            'monitorRevision' => $monitorRevision,
        ];
    }
}
