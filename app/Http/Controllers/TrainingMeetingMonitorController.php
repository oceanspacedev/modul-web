<?php

namespace App\Http\Controllers;

use App\Models\Training;
use App\Models\TrainingMeetingEvent;
use App\Models\TrainingMeetingMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Authorize('update', 'training')]
class TrainingMeetingMonitorController extends Controller
{
    private const ISO_TIME = 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D';

    private const CLOCK_TOLERANCE_SECONDS = 120;

    public function start(Request $request, Training $training): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'uuid'],
            'meeting_code' => ['required', 'string', 'max:128'],
            'account_revision' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/D'],
            'observed_at' => ['sometimes', 'required', 'date', self::ISO_TIME],
        ]);
        $id = strtolower($validated['id']);
        $userId = $request->user()->getKey();

        return DB::transaction(function () use ($training, $validated, $id, $userId) {
            // Use the training lock so simultaneous starts cannot create two observers.
            $training = Training::lockForUpdate()->findOrFail($training->getKey());
            $meetingCode = $training->meetingCode();
            $revision = $this->activeRevision($training);
            if ($revision === null) {
                return $this->monitorUnavailable();
            }
            if ($meetingCode !== $validated['meeting_code'] || $revision !== $validated['account_revision']) {
                return $this->meetingChanged();
            }

            $now = now();
            $existing = TrainingMeetingMonitor::find($id);
            if ($existing !== null) {
                abort_unless($existing->training_id === $training->getKey() && $existing->user_id === $userId, 404);
                if ($existing->meeting_code !== $meetingCode || $existing->account_revision !== $revision) {
                    return $this->meetingChanged();
                }

                return $existing->isActiveAt($now)
                    ? response()->json(['id' => $id])
                    : $this->monitorExpired();
            }

            $hasActiveMonitor = TrainingMeetingMonitor::where('training_id', $training->getKey())
                ->where('meeting_code', $meetingCode)
                ->where('account_revision', $revision)
                ->where('source', TrainingMeetingMonitor::SOURCE_LIVE)
                ->whereNull('ended_at')
                ->where('last_seen_at', '>', $now->copy()->subSeconds(TrainingMeetingMonitor::IDLE_SECONDS))
                ->exists();
            if ($hasActiveMonitor) {
                return response()->json([
                    'code' => 'monitor_busy',
                    'message' => 'Monitoring meeting sedang berjalan di halaman lain.',
                ], 409);
            }

            TrainingMeetingMonitor::create([
                'id' => $id,
                'training_id' => $training->getKey(),
                'user_id' => $userId,
                'provider' => $training->meetingPlatform(),
                'meeting_code' => $meetingCode,
                'source' => TrainingMeetingMonitor::SOURCE_LIVE,
                'account_revision' => $revision,
                'started_at' => $this->boundaryTime($validated['observed_at'] ?? null, $now),
                'last_seen_at' => $now,
            ]);

            return response()->json(['id' => $id]);
        });
    }

    public function sync(Request $request, Training $training, string $monitor): JsonResponse|Response
    {
        $validated = $request->validate([
            'events' => ['present', 'array', 'list', 'max:100'],
            'events.*' => ['array:id,type,observed_at,participant_key,name,camera_on'],
            'events.*.id' => ['required', 'uuid', 'distinct:ignore_case'],
            'events.*.type' => ['required', Rule::in(TrainingMeetingEvent::TYPES)],
            'events.*.observed_at' => ['required', 'date', self::ISO_TIME],
            'events.*.participant_key' => ['required', 'string', 'max:128'],
            'events.*.name' => ['required', 'string', 'max:255'],
            'events.*.camera_on' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($request, $training, $monitor, $validated) {
            $training = Training::lockForUpdate()->findOrFail($training->getKey());
            $session = $this->ownedMonitor($request, $training, $monitor);
            $revision = $this->activeRevision($training);
            if ($revision === null) {
                return $this->monitorUnavailable();
            }
            if ($session->meeting_code !== $training->meetingCode()
                || $session->account_revision !== $revision) {
                return $this->meetingChanged();
            }

            $now = now();
            if (! $session->isActiveAt($now)) {
                return $this->monitorExpired();
            }

            $earliest = $session->started_at->copy()->subSeconds(self::CLOCK_TOLERANCE_SECONDS);
            $latest = $now->copy()->addSeconds(self::CLOCK_TOLERANCE_SECONDS);
            $rows = [];
            foreach ($validated['events'] as $index => $event) {
                $observedAt = $this->parseTime($event['observed_at']);
                if ($observedAt->lessThan($earliest) || $observedAt->greaterThan($latest)) {
                    throw ValidationException::withMessages([
                        "events.$index.observed_at" => 'Waktu aktivitas berada di luar sesi monitoring.',
                    ]);
                }

                $rows[] = [
                    'monitor_id' => $session->getKey(),
                    'event_id' => strtolower($event['id']),
                    'type' => $event['type'],
                    'observed_at' => $observedAt->format('Y-m-d H:i:s.v'),
                    'participant_key' => $event['participant_key'],
                    'name' => $event['name'],
                    'camera_on' => $event['camera_on'] ?? null,
                ];
            }

            // A retried batch must leave the original observation intact.
            TrainingMeetingEvent::upsert($rows, ['monitor_id', 'event_id'], ['event_id']);
            $session->update(['last_seen_at' => $now]);

            return response()->noContent();
        });
    }

    public function end(Request $request, Training $training, string $monitor): Response
    {
        $validated = $request->validate([
            'observed_at' => ['sometimes', 'required', 'date', self::ISO_TIME],
        ]);

        return DB::transaction(function () use ($request, $training, $monitor, $validated) {
            $training = Training::lockForUpdate()->findOrFail($training->getKey());
            $session = $this->ownedMonitor($request, $training, $monitor);
            $now = now();
            if ($session->account_revision === $this->activeRevision($training)
                && $session->meeting_code === $training->meetingCode()
                && $session->isActiveAt($now)) {
                $boundary = $this->boundaryTime($validated['observed_at'] ?? null, $now);
                $session->update(['ended_at' => $boundary->max($session->started_at)]);
            }

            return response()->noContent();
        });
    }

    private function ownedMonitor(Request $request, Training $training, string $id): TrainingMeetingMonitor
    {
        return TrainingMeetingMonitor::where('training_id', $training->getKey())
            ->where('user_id', $request->user()->getKey())
            ->findOrFail(strtolower($id));
    }

    private function activeRevision(Training $training): ?string
    {
        $account = $training->hasLiveMeetingMonitoring() ? $training->meetingAccount()->first() : null;

        return $account === null ? null : $training->meetingMonitorRevision($account);
    }

    private function boundaryTime(?string $value, Carbon $now): Carbon
    {
        if ($value === null) {
            return $now;
        }

        $observedAt = $this->parseTime($value);
        if ($observedAt->lessThan($now->copy()->subSeconds(self::CLOCK_TOLERANCE_SECONDS))
            || $observedAt->greaterThan($now->copy()->addSeconds(self::CLOCK_TOLERANCE_SECONDS))) {
            throw ValidationException::withMessages(['observed_at' => 'Waktu perangkat tidak sesuai dengan waktu server.']);
        }

        return $observedAt->min($now);
    }

    private function parseTime(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }

    private function monitorExpired(): JsonResponse
    {
        return response()->json([
            'code' => 'monitor_expired',
            'message' => 'Sesi monitoring meeting sudah berakhir atau terputus.',
        ], 409);
    }

    private function meetingChanged(): JsonResponse
    {
        return response()->json([
            'code' => 'meeting_changed',
            'message' => 'Pengaturan meeting telah berubah. Muat ulang halaman monitoring.',
        ], 409);
    }

    private function monitorUnavailable(): JsonResponse
    {
        return response()->json([
            'code' => 'monitor_unavailable',
            'message' => 'Aktifkan monitoring dan pilih akun penyelenggara di pengaturan pelatihan.',
        ], 409);
    }
}
