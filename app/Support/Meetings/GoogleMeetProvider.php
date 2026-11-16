<?php

namespace App\Support\Meetings;

use App\Models\MeetingAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Google Meet. Kehadiran ditarik dari Meet REST API (conferenceRecords → participants → participantSessions)
 * memakai service account Google Workspace dengan domain-wide delegation yang bertindak atas nama penyelenggara
 * rapat. API ini tidak memuat status kamera.
 */
class GoogleMeetProvider extends MeetingProvider
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API_URL = 'https://meet.googleapis.com/v2';

    private const SCOPE = 'https://www.googleapis.com/auth/meetings.space.readonly';

    public function key(): string
    {
        return 'google_meet';
    }

    public function label(): string
    {
        return 'Google Meet';
    }

    public function matchesHost(string $host): bool
    {
        return $host === 'meet.google.com';
    }

    public function meetingCode(?string $link): ?string
    {
        $parts = $this->joinUrlParts($link);

        return $parts !== null && preg_match('#^/([a-z]{3}-[a-z]{4}-[a-z]{3})/?$#D', $parts['path'] ?? '', $matches)
            ? $matches[1]
            : null;
    }

    public function credentialFields(): array
    {
        return [
            'organizer_email' => [
                'label' => 'Email penyelenggara rapat',
                'secret' => false,
                'max' => 255,
                'help' => 'Akun Google Workspace yang membuat rapat.',
            ],
            'service_account_json' => [
                'label' => 'Kunci JSON service account',
                'secret' => true,
                'multiline' => true,
                'max' => 8192,
                'help' => 'Service account dengan domain-wide delegation untuk scope meetings.space.readonly.',
            ],
        ];
    }

    public function credentialErrors(array $credentials): array
    {
        $errors = [];
        if (isset($credentials['organizer_email']) && ! filter_var($credentials['organizer_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['organizer_email'] = 'Email penyelenggara rapat tidak valid.';
        }
        if (isset($credentials['service_account_json']) && $this->serviceAccount($credentials['service_account_json']) === null) {
            $errors['service_account_json'] = 'Tempel isi berkas kunci JSON service account (memuat client_email dan private_key).';
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
        $events = [];

        // Satu kode rapat bisa dipakai berkali-kali; setiap pemakaian adalah satu conference record.
        $records = $this->pages($api, 'conferenceRecords', 'conferenceRecords', [
            'filter' => 'space.meeting_code = "'.$meetingCode.'"',
            'pageSize' => 100,
        ]);
        foreach ($records as $record) {
            foreach ($this->pages($api, $record['name'].'/participants', 'participants', ['pageSize' => 250]) as $participant) {
                $name = $participant['signedinUser']['displayName']
                    ?? $participant['anonymousUser']['displayName']
                    ?? $participant['phoneUser']['displayName']
                    ?? 'Peserta Google Meet';
                $participantKey = mb_substr(basename($participant['name']), 0, 128);

                // Peserta yang keluar lalu masuk lagi punya beberapa sesi.
                $sessions = $this->pages($api, $participant['name'].'/participantSessions', 'participantSessions', ['pageSize' => 250]);
                foreach ($sessions as $session) {
                    foreach (['join' => 'startTime', 'leave' => 'endTime'] as $type => $field) {
                        if (blank($session[$field] ?? null)) {
                            continue;
                        }
                        $events[] = [
                            'key' => $session['name'].':'.$type,
                            'type' => $type,
                            'observed_at' => Carbon::parse($session[$field]),
                            'participant_key' => $participantKey,
                            'name' => mb_substr($name, 0, 255),
                            'camera_on' => null,
                        ];
                    }
                }
            }
        }

        return $events;
    }

    /** @return array{client_email: string, private_key: string}|null */
    private function serviceAccount(?string $json): ?array
    {
        $key = json_decode($json ?? '', true);

        return is_array($key)
            && is_string($key['client_email'] ?? null)
            && is_string($key['private_key'] ?? null)
            && openssl_pkey_get_private($key['private_key']) !== false
                ? ['client_email' => $key['client_email'], 'private_key' => $key['private_key']]
                : null;
    }

    /** Token akses lewat JWT bearer grant: service account bertindak sebagai penyelenggara rapat. */
    private function accessToken(MeetingAccount $account): string
    {
        $key = $this->serviceAccount($account->credential('service_account_json'));
        if ($key === null) {
            throw new MeetingApiException('Kunci service account Google tidak valid. Perbarui kredensial akun meeting.');
        }

        $now = now()->timestamp;
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'iss' => $key['client_email'],
            'sub' => $account->credential('organizer_email'),
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]);
        if (! openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new MeetingApiException('Kunci service account Google tidak dapat dipakai untuk menandatangani permintaan.');
        }
        $assertion = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $response = Http::asForm()->acceptJson()->timeout(30)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);
        if ($response->failed() || blank($response->json('access_token'))) {
            throw new MeetingApiException('Google menolak kredensial akun ini: '
                .($response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status())
                .'. Periksa domain-wide delegation dan email penyelenggara.');
        }

        return $response->json('access_token');
    }

    private function api(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30)->baseUrl(self::API_URL);
    }

    /**
     * Semua halaman sebuah daftar.
     *
     * @return list<array<string, mixed>>
     */
    private function pages(PendingRequest $api, string $path, string $field, array $query): array
    {
        $items = [];
        $pageToken = null;
        do {
            $response = (clone $api)->get($path, [...$query, ...($pageToken === null ? [] : ['pageToken' => $pageToken])]);
            if ($response->failed()) {
                throw new MeetingApiException('Google Meet menolak permintaan data kehadiran: '
                    .($response->json('error.message') ?? 'HTTP '.$response->status()).'.');
            }
            $items = [...$items, ...($response->json($field) ?? [])];
            $pageToken = $response->json('nextPageToken');
        } while (filled($pageToken));

        return $items;
    }
}
