<?php

namespace App\Support\Meetings;

use App\Models\MeetingAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Microsoft Teams. Kehadiran ditarik dari laporan kehadiran Microsoft Graph (onlineMeetings → attendanceReports →
 * attendanceRecords) memakai aplikasi Entra dengan izin aplikasi OnlineMeetings.Read.All dan
 * OnlineMeetingArtifact.Read.All, serta application access policy untuk penyelenggara rapat. Laporan baru
 * tersedia setelah rapat berakhir.
 */
class TeamsProvider extends MeetingProvider
{
    private const API_URL = 'https://graph.microsoft.com/v1.0';

    public function key(): string
    {
        return 'teams';
    }

    public function label(): string
    {
        return 'Microsoft Teams';
    }

    public function matchesHost(string $host): bool
    {
        return in_array($host, ['teams.microsoft.com', 'teams.live.com', 'teams.cloud.microsoft'], true);
    }

    /**
     * ID rapat pada tautan pendek (/meet/<id>), atau ID thread pada tautan lama (/l/meetup-join/19:meeting_...).
     */
    public function meetingCode(?string $link): ?string
    {
        $parts = $this->joinUrlParts($link);
        $path = $parts['path'] ?? '';

        if ($parts === null) {
            return null;
        }
        if (preg_match('#^/meet/([0-9]{9,20})/?$#D', $path, $matches)) {
            return $matches[1];
        }
        if (preg_match('#^/l/meetup-join/([^/]+)/#', $path, $matches)) {
            $thread = rawurldecode($matches[1]);

            return preg_match('/^19:meeting_[A-Za-z0-9_\-]{1,100}@thread\.v2$/D', $thread) ? $thread : null;
        }

        return null;
    }

    public function credentialFields(): array
    {
        return [
            'tenant_id' => ['label' => 'Tenant ID', 'secret' => false, 'max' => 100],
            'client_id' => ['label' => 'Client ID aplikasi', 'secret' => false, 'max' => 100],
            'client_secret' => ['label' => 'Client Secret', 'secret' => true],
            'organizer_id' => [
                'label' => 'Object ID penyelenggara rapat',
                'secret' => false,
                'max' => 100,
                'help' => 'Object ID pengguna di Microsoft Entra yang membuat rapat dan tercakup application access policy.',
            ],
        ];
    }

    public function credentialErrors(array $credentials): array
    {
        $errors = [];
        // Nilai ini menjadi bagian URL permintaan.
        foreach (['tenant_id' => '/^[A-Za-z0-9.\-]+$/D', 'organizer_id' => '/^[A-Za-z0-9\-]+$/D'] as $field => $pattern) {
            if (isset($credentials[$field]) && preg_match($pattern, $credentials[$field]) !== 1) {
                $errors[$field] = "{$this->credentialFields()[$field]['label']} tidak valid.";
            }
        }

        return $errors;
    }

    public function supportsAttendanceSync(): bool
    {
        return true;
    }

    public function fetchAttendance(MeetingAccount $account, string $link, string $meetingCode): array
    {
        $api = $this->api($this->accessToken($account));
        $meetings = 'users/'.$account->credential('organizer_id').'/onlineMeetings';

        $filter = ctype_digit($meetingCode)
            ? "joinMeetingIdSettings/joinMeetingId eq '{$meetingCode}'"
            : "JoinWebUrl eq '".str_replace("'", "''", $link)."'";
        $meetingId = $this->collection($api, $meetings, ['$filter' => $filter])[0]['id'] ?? null;
        if ($meetingId === null) {
            throw new MeetingApiException('Rapat Teams ini tidak ditemukan pada akun penyelenggara yang dipilih.');
        }

        $events = [];
        $reports = $meetings.'/'.rawurlencode($meetingId).'/attendanceReports';
        // Setiap kali rapat berakhir, Teams membuat satu laporan (paling banyak 50 laporan terakhir).
        foreach ($this->collection($api, $reports) as $report) {
            foreach ($this->collection($api, $reports.'/'.rawurlencode($report['id']).'/attendanceRecords') as $record) {
                $name = mb_substr($record['identity']['displayName'] ?? $record['emailAddress'] ?? 'Peserta Teams', 0, 255);
                $participantKey = mb_substr($record['identity']['id'] ?? $record['emailAddress'] ?? $name, 0, 128);
                $prefix = $report['id'].':'.$participantKey.':';
                $event = fn (string $key, string $type, string $time, ?bool $camera = null) => [
                    'key' => $prefix.$key,
                    'type' => $type,
                    'observed_at' => Carbon::parse($time),
                    'participant_key' => $participantKey,
                    'name' => $name,
                    'camera_on' => $camera,
                ];

                foreach ($record['attendanceIntervals'] ?? [] as $interval) {
                    if (blank($interval['joinDateTime'] ?? null)) {
                        continue;
                    }
                    $events[] = $event($interval['joinDateTime'].':join', 'join', $interval['joinDateTime']);
                    if (filled($interval['leaveDateTime'] ?? null)) {
                        $events[] = $event($interval['joinDateTime'].':leave', 'leave', $interval['leaveDateTime']);
                    }
                }

                // Laporan memuat kapan kamera dinyalakan atau dimatikan, bila Teams mencatatnya.
                foreach ($record['engagements'] ?? [] as $engagement) {
                    $camera = ['cameraOn' => true, 'cameraOff' => false][$engagement['engagementSubType'] ?? ''] ?? null;
                    if (($engagement['engagementType'] ?? null) === 'camera' && $camera !== null && filled($engagement['dateTime'] ?? null)) {
                        $events[] = $event($engagement['dateTime'].':camera', 'camera', $engagement['dateTime'], $camera);
                    }
                }
            }
        }

        return $events;
    }

    /** Token aplikasi (client credentials) untuk Microsoft Graph. */
    private function accessToken(MeetingAccount $account): string
    {
        $response = Http::asForm()->acceptJson()->timeout(30)->post(
            'https://login.microsoftonline.com/'.rawurlencode((string) $account->credential('tenant_id')).'/oauth2/v2.0/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => $account->credential('client_id'),
                'client_secret' => $account->credential('client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
            ],
        );
        if ($response->failed() || blank($response->json('access_token'))) {
            throw new MeetingApiException('Microsoft menolak kredensial akun ini: '
                .($response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status()).'.');
        }

        return $response->json('access_token');
    }

    private function api(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    /**
     * Semua halaman sebuah koleksi Graph.
     *
     * @return list<array<string, mixed>>
     */
    private function collection(PendingRequest $api, string $path, array $query = []): array
    {
        $items = [];
        $url = self::API_URL.'/'.$path;
        do {
            $response = (clone $api)->get($url, $query);
            if ($response->failed()) {
                throw new MeetingApiException('Microsoft Teams menolak permintaan data kehadiran: '
                    .($response->json('error.message') ?? 'HTTP '.$response->status())
                    .'. Periksa izin aplikasi dan application access policy penyelenggara.');
            }
            $items = [...$items, ...($response->json('value') ?? [])];
            $url = $response->json('@odata.nextLink');
            $query = [];
        } while (is_string($url) && str_starts_with($url, self::API_URL.'/'));

        return $items;
    }
}
