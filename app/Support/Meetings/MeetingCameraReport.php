<?php

namespace App\Support\Meetings;

use App\Enums\CameraStatus;
use App\Models\Training;
use App\Models\TrainingMeetingEvent;
use App\Models\TrainingMeetingMonitor;
use Illuminate\Support\Carbon;

/**
 * Rekap kamera peserta menurut status kamera yang terekam monitoring meeting (Zoom SDK langsung, atau laporan
 * kehadiran platform yang membawa status kamera). Porsi waktu kamera menyala selama peserta ada di meeting menentukan
 * statusnya (lihat CameraStatus). Hasilnya berbentuk sama dengan pindaian screenshot AI, jadi pelatih memeriksanya di
 * tabel verifikasi yang sama sebelum disimpan.
 */
final class MeetingCameraReport
{
    /** Lebih dari ini nama dari platform dianggap bukan nama orang (mis. log aneh) dan dilewati. */
    private const MAX_NAME_LENGTH = 255;

    /** Nama pengganti platform untuk peserta tanpa nama: bisa dipakai banyak orang, jadi tidak disambungkan. */
    private const GENERIC_NAMES = ['Peserta Zoom', 'Peserta Teams'];

    /**
     * @return list<array{zoom_name: string, participant_id: ?int, official_name: ?string, id_karyawan: ?string, similarity_score: int, match_reason: string, visual_evidence: string, confidence: string, is_registered: bool, camera_status: string, camera_on_percent: int, on_cam_seconds: int, total_seconds: int, off_cam_count: int}>
     */
    public static function forTraining(Training $training): array
    {
        $monitors = TrainingMeetingMonitor::where('training_id', $training->id)
            ->get(['id', 'meeting_code', 'started_at', 'last_seen_at', 'ended_at'])
            ->keyBy('id');

        if ($monitors->isEmpty()) {
            return [];
        }

        $people = self::cameraTime($monitors->all(), $monitors->keys()->all());
        if ($people === []) {
            return [];
        }

        $participants = $training->participants()
            ->with('user:id,nama_lengkap,employee_id')
            ->orderBy('id')
            ->get(['id', 'training_id', 'user_id'])
            ->map(fn ($participant) => [
                'id' => $participant->id,
                'name' => $participant->user->nama_lengkap ?? 'Peserta',
                'id_karyawan' => $participant->user->employee_id ?? '-',
            ])
            ->all();

        // Satu baris per peserta terdaftar (nama yang berbeda untuk orang yang sama digabung), nama yang tidak
        // cocok tetap satu baris per nama supaya pelatih bisa memasangkannya sendiri.
        $rows = [];
        foreach ($people as $person) {
            $match = null;
            foreach ($person['match_names'] as $name) {
                $candidate = MeetingNameMatcher::match($name, $participants);
                if ($candidate !== null && ($match === null || $candidate['score'] > $match['score'])) {
                    $match = $candidate;
                }
            }
            $key = $match !== null ? 'p:'.$match['id'] : 'n:'.$person['key'];

            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'names' => [],
                    'match' => $match,
                    'on' => 0,
                    'off' => 0,
                    'count' => 0,
                    'first_off_at' => null,
                ];
            }

            $rows[$key]['names'][$person['name']] = true;
            $rows[$key]['on'] += $person['on'];
            $rows[$key]['off'] += $person['off'];
            $rows[$key]['count'] += $person['count'];
            if ($person['first_off_at'] !== null) {
                $rows[$key]['first_off_at'] = $rows[$key]['first_off_at']?->min($person['first_off_at']) ?? $person['first_off_at'];
            }
            if ($match !== null && $match['score'] > $rows[$key]['match']['score']) {
                $rows[$key]['match'] = $match;
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $match = $row['match'];
            $total = $row['on'] + $row['off'];
            $status = CameraStatus::fromChecks($row['on'], $total);
            $percent = CameraStatus::percent($row['on'], $total);

            if ($row['on'] === 0) {
                $evidence = 'Kamera tidak pernah menyala ('.self::minutes($total).' di meeting)';
            } elseif ($row['on'] >= $total) {
                $evidence = 'Kamera menyala sepanjang '.self::minutes($total).' di meeting';
            } else {
                $evidence = 'Kamera menyala '.self::minutes($row['on']).' dari '.self::minutes($total).' ('.$percent.'%)';
                if ($row['count'] > 0) {
                    $evidence .= ', mati '.$row['count'].'x, pertama pukul '.$row['first_off_at']->format('H:i').' WIB';
                }
            }

            $result[] = [
                'zoom_name' => implode(' / ', array_keys($row['names'])),
                'participant_id' => $match['id'] ?? null,
                'official_name' => $match['name'] ?? null,
                'id_karyawan' => $match['id_karyawan'] ?? null,
                'similarity_score' => $match['score'] ?? 0,
                'match_reason' => $match === null ? 'Nama di meeting tidak cocok dengan peserta terdaftar' : '',
                'visual_evidence' => $evidence,
                'confidence' => 'high',
                'is_registered' => $match !== null,
                'camera_status' => $status->value,
                'camera_on_percent' => $percent,
                'on_cam_seconds' => $row['on'],
                'total_seconds' => $total,
                'off_cam_count' => $row['count'],
            ];
        }

        // Peserta terdaftar dulu, lalu yang paling jarang menyalakan kamera.
        usort($result, fn (array $a, array $b) => [$b['is_registered'], $a['camera_on_percent'], $a['zoom_name']]
            <=> [$a['is_registered'], $b['camera_on_percent'], $b['zoom_name']]);

        return $result;
    }

    /**
     * Hitung berapa lama setiap peserta meeting menyalakan dan mematikan kamera, dan berapa kali mematikannya.
     *
     * Orang dikenali dari ID peserta di rapat (participant_key), jadi ganti nama tidak memecah catatannya dan dua
     * orang bernama sama tetap terpisah. Petugas yang menyambung ulang melihat ID yang sama sebagai "present", jadi
     * kamera yang tetap mati tidak dihitung mati lagi. Zoom memberi ID baru saat peserta keluar lalu masuk lagi; ID
     * baru itu disambungkan ke orang yang sudah keluar dengan nama yang sama. Waktu hanya dihitung selama peserta ada
     * di rapat, status kameranya diketahui, dan ada monitoring yang merekam.
     *
     * @param  array<string, TrainingMeetingMonitor>  $monitors
     * @param  list<string>  $monitorIds
     * @return list<array{key: string, name: string, match_names: list<string>, on: int, off: int, count: int, first_off_at: ?Carbon}>
     */
    private static function cameraTime(array $monitors, array $monitorIds): array
    {
        $events = TrainingMeetingEvent::query()
            ->toBase()
            ->whereIn('monitor_id', $monitorIds)
            ->orderBy('observed_at')
            ->orderBy('id')
            ->cursor();

        $people = [];
        $aliases = [];
        $leftByName = [];
        foreach ($events as $event) {
            $name = trim((string) $event->name);
            if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
                continue;
            }

            $monitor = $monitors[$event->monitor_id];
            $nameKey = $monitor->meeting_code.'|'.mb_strtolower($name);
            $id = $monitor->meeting_code.'|'.$event->participant_key;

            // ID baru untuk nama yang tadi keluar: orang yang sama masuk ulang.
            if (! isset($people[$id]) && ! isset($aliases[$id]) && isset($leftByName[$nameKey]) && ! in_array($name, self::GENERIC_NAMES, true)) {
                $aliases[$id] = $leftByName[$nameKey];
            }
            $id = $aliases[$id] ?? $id;
            unset($leftByName[$nameKey]);

            $at = Carbon::parse($event->observed_at);
            $person = $people[$id] ?? [
                'key' => $id,
                'names' => [],
                'on' => 0,
                'off' => 0,
                'count' => 0,
                'first_off_at' => null,
                'camera_on' => null,
                'since' => null,
                'since_monitor' => null,
                'until' => null,
            ];
            $person['names'][$name] = true;
            $person['name'] = $name;

            // Rekaman berpindah ke sesi monitoring lain: jeda tanpa monitoring tidak ikut dihitung.
            if ($person['since'] !== null && $person['since_monitor'] !== $event->monitor_id) {
                $person = self::closePeriod($person, $person['until']);
            }

            if ($event->type === 'leave') {
                // Status kamera diingat sampai terlihat berubah; waktunya hanya dihitung selama di rapat.
                $person = self::closePeriod($person, $at);
                $leftByName[$nameKey] = $id;
            } else {
                if ($event->camera_on !== null) {
                    $cameraOn = (bool) $event->camera_on;

                    if (! $cameraOn && $person['camera_on'] !== false) {
                        $person['count']++;
                        $person['first_off_at'] ??= $at;
                    }
                    if ($person['camera_on'] !== $cameraOn) {
                        $person = self::closePeriod($person, $at);
                    }
                    $person['camera_on'] = $cameraOn;
                }

                if ($person['since'] === null && $person['camera_on'] !== null) {
                    $person['since'] = $at;
                    $person['since_monitor'] = $monitor->id;
                    $person['until'] = $monitor->ended_at ?? $monitor->last_seen_at;
                }
            }

            $people[$id] = $person;
        }

        $result = [];
        foreach ($people as $person) {
            // Masih di rapat saat monitoring selesai: dihitung sampai akhir sesi monitoring yang merekamnya.
            $person = self::closePeriod($person, $person['until']);

            if ($person['on'] + $person['off'] === 0) {
                continue;
            }

            $result[] = [
                'key' => $person['key'],
                'name' => implode(' / ', array_keys($person['names'])),
                'match_names' => array_keys($person['names']),
                'on' => $person['on'],
                'off' => $person['off'],
                'count' => $person['count'],
                'first_off_at' => $person['first_off_at'],
            ];
        }

        return $result;
    }

    /** Tutup rentang waktu yang sedang berjalan dan tambahkan lamanya ke waktu kamera menyala atau mati. */
    private static function closePeriod(array $person, ?Carbon $until): array
    {
        if ($person['since'] !== null && $until !== null && $until->greaterThan($person['since'])) {
            $person[$person['camera_on'] ? 'on' : 'off'] += (int) $person['since']->diffInSeconds($until, true);
        }

        $person['since'] = null;
        $person['since_monitor'] = null;
        $person['until'] = null;

        return $person;
    }

    private static function minutes(int $seconds): string
    {
        return $seconds < 60 ? 'kurang dari 1 menit' : '±'.(int) round($seconds / 60).' menit';
    }
}
