<?php

namespace App\Support\Ai;

use App\Models\AiSetting;

/**
 * Pengaturan AI yang berlaku: isian di Pengaturan AI, dengan GEMINI_API_KEY / GEMINI_MODEL di .env sebagai cadangan
 * selama admin belum mengisinya.
 */
class AiSettings
{
    public static function provider(): AiProvider
    {
        return AiProviders::find(AiSetting::value(AiSetting::PROVIDER)) ?? AiProviders::find('gemini');
    }

    /** API key yang tersimpan di Pengaturan AI saja (tanpa cadangan .env). */
    public static function storedApiKey(AiProvider $provider): ?string
    {
        return AiSetting::secret(AiSetting::apiKeyKey($provider->key()));
    }

    public static function storedModel(AiProvider $provider): ?string
    {
        return AiSetting::value(AiSetting::modelKey($provider->key()));
    }

    public static function apiKey(?AiProvider $provider = null): ?string
    {
        $provider ??= self::provider();

        return self::storedApiKey($provider) ?? $provider->envApiKey();
    }

    public static function model(?AiProvider $provider = null): ?string
    {
        $provider ??= self::provider();

        return self::storedModel($provider) ?? $provider->defaultModel();
    }

    /** Fitur AI bisa dipakai: provider aktif punya API key dan model. */
    public static function isConfigured(): bool
    {
        return filled(self::apiKey()) && filled(self::model());
    }

    /** Asal API key sebuah provider: 'pengaturan', 'env', atau null bila belum ada. */
    public static function source(?AiProvider $provider = null): ?string
    {
        $provider ??= self::provider();

        return match (true) {
            filled(self::storedApiKey($provider)) => 'pengaturan',
            filled($provider->envApiKey()) => 'env',
            default => null,
        };
    }

    public static function notConfiguredMessage(): string
    {
        return 'AI belum diatur. Admin dapat mengisi API key dan model di Pengaturan AI.';
    }
}
