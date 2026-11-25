<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Pengaturan AI berbentuk key → value, diubah admin di Pengaturan AI (bukan di .env). API key disimpan terenkripsi;
 * cache hanya memegang nilai terenkripsinya.
 */
class AiSetting extends Model
{
    public const PROVIDER = 'provider';

    private const CACHE_KEY = 'ai-settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public static function apiKeyKey(string $provider): string
    {
        return $provider.'.api_key';
    }

    public static function modelKey(string $provider): string
    {
        return $provider.'.model';
    }

    public static function value(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    /** Nilai rahasia yang sudah didekripsi; null bila kosong atau tidak bisa dibuka (mis. APP_KEY berganti). */
    public static function secret(string $key): ?string
    {
        $encrypted = self::value($key);
        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Simpan beberapa pengaturan sekaligus; nilai kosong menghapus pengaturannya. Kunci berakhiran ".api_key"
     * dienkripsi.
     *
     * @param  array<string, ?string>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (blank($value)) {
                self::whereKey($key)->delete();

                continue;
            }

            $value = trim((string) $value);
            self::updateOrCreate(['key' => $key], [
                'value' => str_ends_with($key, '.api_key') ? Crypt::encryptString($value) : $value,
            ]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
