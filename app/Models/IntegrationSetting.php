<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Pengaturan integrasi dengan sistem lain, berbentuk key → value dan diubah dari aplikasi (bukan di .env). Tabelnya
 * dipakai bersama semua integrasi: setiap integrasi memakai awalan sendiri pada key-nya, mis. "helpdesk.webhook_url"
 * (lihat App\Support\HelpdeskConnection). Key berakhiran "_secret" disimpan terenkripsi; cache hanya memegang nilai
 * yang sudah diamankan.
 */
class IntegrationSetting extends Model
{
    private const ENCRYPTED_SUFFIX = '_secret';

    private const CACHE_KEY = 'integration-settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public static function value(string $key): ?string
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->pluck('value', 'key')->all());

        return $all[$key] ?? null;
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
     * Simpan beberapa pengaturan sekaligus; nilai kosong menghapus pengaturannya.
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
                'value' => str_ends_with($key, self::ENCRYPTED_SUFFIX) ? Crypt::encryptString($value) : $value,
            ]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
