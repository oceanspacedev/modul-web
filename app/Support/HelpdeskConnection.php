<?php

namespace App\Support;

use App\Models\IntegrationSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Koneksi ke helpdesk (kontrak integrasi helpdesk §1.1): admin membuat satu "kode koneksi" di Pengaturan Helpdesk,
 * menempelkannya di helpdesk, lalu helpdesk mendaftarkan URL dan rahasia webhook-nya sendiri lewat PUT /webhook.
 * Tidak ada yang perlu disalin ke .env.
 *
 * Pengaturan dari aplikasi selalu menang; HELPDESK_API_TOKEN / HELPDESK_WEBHOOK_URL / HELPDESK_WEBHOOK_SECRET di .env
 * hanya dipakai selama pengaturan aplikasinya kosong (instalasi lama).
 */
final class HelpdeskConnection
{
    /** Awalan kode koneksi; angka = versi format isi kode. */
    public const CODE_PREFIX = 'hd1_';

    // Key di integration_settings; semuanya berawalan "helpdesk." karena tabelnya dipakai bersama integrasi lain.
    public const TOKEN_HASH = 'helpdesk.token_hash';

    public const TOKEN_ISSUED_AT = 'helpdesk.token_issued_at';

    /** Token sebelum kode terakhir dibuat; berlaku sampai helpdesk memakai token baru atau masa tenggangnya habis. */
    public const PREVIOUS_TOKEN_HASH = 'helpdesk.previous_token_hash';

    public const PREVIOUS_TOKEN_EXPIRES_AT = 'helpdesk.previous_token_expires_at';

    public const WEBHOOK_URL = 'helpdesk.webhook_url';

    /** Berakhiran "_secret": IntegrationSetting menyimpannya terenkripsi. */
    public const WEBHOOK_SECRET = 'helpdesk.webhook_secret';

    public const WEBHOOK_REGISTERED_AT = 'helpdesk.webhook_registered_at';

    /** Hasil pengiriman webhook terakhir, untuk halaman Pengaturan Helpdesk. */
    public const LAST_DELIVERY_AT = 'helpdesk.last_delivery_at';

    public const LAST_DELIVERY_ERROR = 'helpdesk.last_delivery_error';

    /** Masa tenggang token lama setelah kode baru dibuat, supaya helpdesk tidak langsung terputus. */
    public const GRACE_HOURS = 24;

    public static function apiBaseUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/helpdesk/v1';
    }

    /**
     * Buat token baru dan kembalikan kode koneksinya. Token hanya ada di kode ini. Token lama (dari kode sebelumnya
     * atau .env) tetap berlaku selama GRACE_HOURS, atau sampai helpdesk memanggil API dengan token baru, dan webhook
     * lama tetap dipakai sampai helpdesk mendaftarkan webhook baru. Rotasi kode jadi tidak memutus helpdesk.
     */
    public static function issueCode(): string
    {
        $token = Str::random(48);
        $previous = match (self::tokenSource()) {
            'app' => IntegrationSetting::value(self::TOKEN_HASH),
            'env' => hash('sha256', (string) config('services.helpdesk.api_token')),
            default => null,
        };

        IntegrationSetting::put([
            self::TOKEN_HASH => hash('sha256', $token),
            self::TOKEN_ISSUED_AT => now()->toIso8601String(),
            self::PREVIOUS_TOKEN_HASH => $previous,
            self::PREVIOUS_TOKEN_EXPIRES_AT => $previous ? now()->addHours(self::GRACE_HOURS)->toIso8601String() : null,
        ]);

        return self::encode([
            'v' => 1,
            // APP_NAME bawaan Laravel tidak bermakna di helpdesk.
            'name' => in_array((string) config('app.name'), ['', 'Laravel'], true) ? 'Modul' : (string) config('app.name'),
            'url' => self::apiBaseUrl(),
            'token' => $token,
        ]);
    }

    public static function disconnect(): void
    {
        IntegrationSetting::put([
            self::TOKEN_HASH => null,
            self::TOKEN_ISSUED_AT => null,
            self::PREVIOUS_TOKEN_HASH => null,
            self::PREVIOUS_TOKEN_EXPIRES_AT => null,
            self::WEBHOOK_URL => null,
            self::WEBHOOK_SECRET => null,
            self::WEBHOOK_REGISTERED_AT => null,
        ]);
    }

    public static function tokenMatches(string $given): bool
    {
        if ($given === '') {
            return false;
        }

        $hash = IntegrationSetting::value(self::TOKEN_HASH);
        if (filled($hash)) {
            $givenHash = hash('sha256', $given);
            if (hash_equals($hash, $givenHash)) {
                // Helpdesk sudah memakai kode baru: token lama tidak perlu menunggu masa tenggangnya habis.
                if (filled(IntegrationSetting::value(self::PREVIOUS_TOKEN_HASH))) {
                    IntegrationSetting::put([self::PREVIOUS_TOKEN_HASH => null, self::PREVIOUS_TOKEN_EXPIRES_AT => null]);
                }

                return true;
            }

            $previous = IntegrationSetting::value(self::PREVIOUS_TOKEN_HASH);

            return filled($previous) && self::previousTokenValidUntil() !== null && hash_equals($previous, $givenHash);
        }

        $env = (string) config('services.helpdesk.api_token');

        return $env !== '' && hash_equals($env, $given);
    }

    /** 'app' (kode koneksi), 'env' (.env lama), atau null (belum terhubung). */
    public static function tokenSource(): ?string
    {
        return match (true) {
            filled(IntegrationSetting::value(self::TOKEN_HASH)) => 'app',
            filled(config('services.helpdesk.api_token')) => 'env',
            default => null,
        };
    }

    public static function tokenIssuedAt(): ?Carbon
    {
        return self::date(self::TOKEN_ISSUED_AT);
    }

    /** Batas berlaku token lama, atau null bila tidak ada token lama yang masih berlaku. */
    public static function previousTokenValidUntil(): ?Carbon
    {
        if (blank(IntegrationSetting::value(self::PREVIOUS_TOKEN_HASH))) {
            return null;
        }

        $until = self::date(self::PREVIOUS_TOKEN_EXPIRES_AT);

        return $until?->isFuture() ? $until : null;
    }

    /** Dipanggil helpdesk (PUT /webhook) dengan token kode koneksi. */
    public static function registerWebhook(string $url, string $secret): void
    {
        IntegrationSetting::put([
            self::WEBHOOK_URL => $url,
            self::WEBHOOK_SECRET => $secret,
            self::WEBHOOK_REGISTERED_AT => now()->toIso8601String(),
        ]);
    }

    public static function webhookUrl(): ?string
    {
        if (self::webhookSource() === 'app') {
            return IntegrationSetting::value(self::WEBHOOK_URL);
        }

        return filled(config('services.helpdesk.webhook_url')) ? (string) config('services.helpdesk.webhook_url') : null;
    }

    public static function webhookSecret(): ?string
    {
        if (self::webhookSource() === 'app') {
            return IntegrationSetting::secret(self::WEBHOOK_SECRET);
        }

        return filled(config('services.helpdesk.webhook_secret')) ? (string) config('services.helpdesk.webhook_secret') : null;
    }

    /**
     * URL dan rahasia webhook selalu diambil berpasangan dari sumber yang sama. Setelah kode koneksi dibuat, nilai .env
     * hanya dipakai selama masa tenggang token lama; setelah itu webhook jalan lagi begitu helpdesk mendaftarkannya.
     */
    public static function webhookSource(): ?string
    {
        return match (true) {
            filled(IntegrationSetting::value(self::WEBHOOK_URL)) && filled(IntegrationSetting::secret(self::WEBHOOK_SECRET)) => 'app',
            self::tokenSource() === 'app' && self::previousTokenValidUntil() === null => null,
            filled(config('services.helpdesk.webhook_url')) && filled(config('services.helpdesk.webhook_secret')) => 'env',
            default => null,
        };
    }

    public static function webhookRegisteredAt(): ?Carbon
    {
        return self::date(self::WEBHOOK_REGISTERED_AT);
    }

    /** Dicatat SendHelpdeskWebhook setiap kali mencoba mengirim. Null error = berhasil. */
    public static function recordDelivery(?string $error): void
    {
        IntegrationSetting::put([
            self::LAST_DELIVERY_AT => now()->toIso8601String(),
            self::LAST_DELIVERY_ERROR => $error === null ? null : mb_substr($error, 0, 300),
        ]);
    }

    /** @return array{at: Carbon, error: ?string}|null */
    public static function lastDelivery(): ?array
    {
        $at = self::date(self::LAST_DELIVERY_AT);

        return $at ? ['at' => $at, 'error' => IntegrationSetting::value(self::LAST_DELIVERY_ERROR)] : null;
    }

    public static function webhookConfigured(): bool
    {
        return self::webhookSource() !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return self::CODE_PREFIX.rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private static function date(string $key): ?Carbon
    {
        $value = IntegrationSetting::value($key);

        return filled($value) ? Carbon::parse($value) : null;
    }
}
