<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan OD Report berbentuk key → value, diubah OD Leader di Pengaturan OD (bukan di .env).
 */
class OdSetting extends Model
{
    public const SHEET_LINK = 'sheets.spreadsheet';

    public const SHEET_URL = 'sheets.url';

    public const SHEET_FROM = 'sheets.from';

    private const CACHE_KEY = 'od-settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public static function value(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    /**
     * Simpan beberapa pengaturan sekaligus; nilai kosong menghapus pengaturannya.
     *
     * @param  array<string, ?string>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            filled($value)
                ? self::updateOrCreate(['key' => $key], ['value' => trim((string) $value)])
                : self::whereKey($key)->delete();
        }

        Cache::forget(self::CACHE_KEY);
    }
}
