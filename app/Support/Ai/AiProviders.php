<?php

namespace App\Support\Ai;

class AiProviders
{
    /** @var array<string, AiProvider>|null */
    private static ?array $providers = null;

    /** @return array<string, AiProvider> */
    public static function all(): array
    {
        return self::$providers ??= collect([
            new GeminiProvider,
            new OpenAiProvider,
            new AnthropicProvider,
        ])->keyBy(fn (AiProvider $provider) => $provider->key())->all();
    }

    public static function find(?string $key): ?AiProvider
    {
        return self::all()[$key ?? ''] ?? null;
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_map(fn (AiProvider $provider) => $provider->label(), self::all());
    }
}
