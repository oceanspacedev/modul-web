<?php

namespace App\Support\Meetings;

use App\Models\MeetingAccount;

class ZoomProvider extends MeetingProvider
{
    public function key(): string
    {
        return 'zoom';
    }

    public function label(): string
    {
        return 'Zoom';
    }

    public function matchesHost(string $host): bool
    {
        return preg_match('/(?:^|\.)(?:zoom\.us|zoom\.com|zoomgov\.com)$/D', $host) === 1;
    }

    public function meetingCode(?string $link): ?string
    {
        $parts = $this->joinUrlParts($link);

        // Meeting SDK yang dipakai untuk pemantauan hanya melayani rapat di zoom.us.
        if ($parts === null || preg_match('/(?:^|\.)zoom\.us$/D', strtolower($parts['host'])) !== 1) {
            return null;
        }

        return preg_match('#^/(?:j|wc/join)/([0-9]{9,11})/?$#D', $parts['path'] ?? '', $matches)
            ? $matches[1]
            : null;
    }

    public function credentialFields(): array
    {
        return [
            'client_id' => ['label' => 'Client ID', 'secret' => false],
            'client_secret' => ['label' => 'Client Secret', 'secret' => true],
        ];
    }

    public function supportsMonitoring(): bool
    {
        return true;
    }

    public function supportsChatImport(): bool
    {
        return true;
    }

    /** Tanda tangan Meeting SDK: petugas masuk sebagai peserta biasa (role 0) ke rapat ini saja. */
    public function joinPayload(MeetingAccount $account, string $meetingCode): array
    {
        $issuedAt = now()->timestamp - 30;
        $expiresAt = $issuedAt + 7200;
        $header = $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode([
            'appKey' => $account->credential('client_id'),
            'mn' => $meetingCode,
            'role' => 0,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'tokenExp' => $expiresAt,
        ], JSON_THROW_ON_ERROR));
        $signed = $this->base64Url(hash_hmac('sha256', "$header.$payload", $account->credential('client_secret'), true));

        return ['signature' => "$header.$payload.$signed"];
    }

    public function parseChat(string $contents): array
    {
        return ZoomPublicChat::parse($contents);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
