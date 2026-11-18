<?php

namespace App\Services;

use App\Jobs\SyncOdSheets;
use App\Models\OdActivity;
use App\Support\OdTeam;
use App\Support\OdWeek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sinkron satu arah Daily Report → spreadsheet Weekly Report, lewat Apps Script yang sama dengan aplikasi OD Report
 * lama (format kiriman tidak berubah, jadi Apps Script tidak perlu di-deploy ulang). Script menulis tiap pekerjaan ke
 * tab bulan ("Nov'26"), blok POSISI + WEEK, dan mengenali barisnya lewat SYNC ID (kolom H).
 *
 * Yang dikirim: aktivitas yang sudah "Simpan Laporan" dan berubah sejak terakhir terkirim, plus SYNC ID aktivitas
 * terkirim yang sudah dihapus (barisnya dikosongkan).
 */
class OdSheetSync
{
    /** Batas pekerjaan per kiriman, supaya Apps Script selesai sebelum batas waktunya. */
    public const BATCH = 100;

    private const STATUS_CACHE = 'od-sheets-sync:status';

    /** Nama tab bulan di spreadsheet tim. */
    private const TAB_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'AGS', 'Sept', 'Okt', 'Nov', 'Des'];

    public static function enabled(): bool
    {
        return filled(config('od-report.sheets.url')) && self::spreadsheetId() !== '';
    }

    /** ID spreadsheet dari link (…/d/{id}/edit) atau ID langsung. */
    public static function spreadsheetId(): string
    {
        $value = trim((string) config('od-report.sheets.spreadsheet'));

        return preg_match('~/d/([\w-]+)~', $value, $match) ? $match[1] : $value;
    }

    private static bool $scheduledAfterResponse = false;

    /**
     * Kirim perubahan di belakang layar (tidak melakukan apa pun bila sinkron belum diatur). Dengan antrean "sync"
     * (lokal) kiriman dijalankan setelah respons, dan kegagalan Google hanya dicatat: menyimpan laporan tidak boleh
     * gagal karena spreadsheet.
     */
    public static function queue(): void
    {
        if (! self::enabled()) {
            return;
        }

        if (config('queue.connections.'.config('queue.default').'.driver') !== 'sync') {
            SyncOdSheets::dispatch();

            return;
        }

        if (! self::$scheduledAfterResponse) {
            self::$scheduledAfterResponse = true;
            app()->terminating(function () {
                self::$scheduledAfterResponse = false;
                try {
                    app(self::class)->run();
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }
    }

    /** @return Builder<OdActivity> */
    public function pending(): Builder
    {
        return OdActivity::query()->reported()
            ->where(fn (Builder $q) => $q->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at'));
    }

    public function pendingCount(): int
    {
        return $this->pending()->count() + DB::table('od_sheet_deletions')->count();
    }

    /**
     * Kirim semua perubahan. Melempar exception bila Apps Script menolak atau tidak bisa dihubungi, supaya job
     * dicoba lagi.
     */
    public function run(): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            $written = $deleted = 0;
            $warnings = [];
            $rounds = 0;

            do {
                $activities = $this->pending()->with('user.position')->orderBy('date')->orderBy('id')->limit(self::BATCH)->get();
                $deletions = DB::table('od_sheet_deletions')->orderBy('id')->limit(self::BATCH)->pluck('sync_key');
                if ($activities->isEmpty() && $deletions->isEmpty()) {
                    break;
                }

                $result = $this->send($activities->map(fn (OdActivity $a) => $this->entry($a))->all(), $deletions->all());
                $failed = collect($result['fail'] ?? [])->map(fn ($key) => (string) $key);

                foreach ($activities as $activity) {
                    if (! $failed->contains($activity->sync_key)) {
                        // Hanya bila belum diubah lagi selama dikirim; perubahan baru tetap menunggu kiriman berikutnya.
                        DB::table('od_activities')->where('id', $activity->id)->where('updated_at', $activity->getRawOriginal('updated_at'))
                            ->update(['synced_at' => now()]);
                    }
                }
                DB::table('od_sheet_deletions')->whereIn('sync_key', $deletions->diff($failed))->delete();

                $written += (int) ($result['a'] ?? 0);
                $deleted += (int) ($result['d'] ?? 0);
                $warnings = [...$warnings, ...array_filter(explode("\n", (string) ($result['log'] ?? '')))];

                if ($failed->isNotEmpty()) {
                    throw new RuntimeException($failed->count().' pekerjaan gagal ditulis: '.Str::limit(implode(' | ', $warnings), 300));
                }
            } while (++$rounds < 50 && ($activities->count() === self::BATCH || $deletions->count() === self::BATCH));

            $this->remember(true, "{$written} pekerjaan ditulis, {$deleted} dihapus".($warnings ? '. Perhatian: '.Str::limit(implode(' | ', $warnings), 200) : ''));
        } catch (\Throwable $e) {
            $this->remember(false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Satu pekerjaan dalam format kiriman aplikasi lama.
     *
     * @return array<string, string|int>
     */
    public function entry(OdActivity $activity): array
    {
        $week = OdWeek::of($activity->date);
        [$year, $month] = explode('-', $week->month());

        return [
            'key' => $activity->sync_key,
            'u' => $activity->user->nama_lengkap,
            'pos' => mb_strtoupper(OdTeam::positionOf($activity->user)),
            'tab' => self::TAB_MONTHS[(int) $month - 1]."'".substr($year, 2),
            'wk' => $week->number(),
            'n' => $activity->name,
            'r' => OdActivity::WEEKLY_STATUS[$activity->result] ?? $activity->result,
            'k' => $activity->kendala ?? '-',
            's' => $activity->solusi ?? '-',
            'ke' => $activity->keterangan ?? '-',
        ];
    }

    /**
     * @return array{state: ?bool, message: string, at: ?string}
     */
    public function status(): array
    {
        return Cache::get(self::STATUS_CACHE, ['state' => null, 'message' => '', 'at' => null]);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<string>  $deletions
     * @return array<string, mixed>
     */
    private function send(array $entries, array $deletions): array
    {
        $response = Http::timeout(120)->acceptJson()->post(config('od-report.sheets.url'), [
            'stamp' => (string) Str::uuid(),
            'weeklyId' => self::spreadsheetId(),
            'entries' => $entries,
            'del' => $deletions,
        ]);

        $result = $response->json();
        if (! is_array($result) || ! array_key_exists('ok', $result)) {
            // Halaman login Google = Web App belum "Siapa saja".
            throw new RuntimeException('Apps Script tidak membalas dengan benar (HTTP '.$response->status().'). Pastikan deployment Web App aksesnya "Siapa saja".');
        }
        if ($result['ok'] !== true) {
            throw new RuntimeException('Apps Script: '.($result['error'] ?? 'gagal tanpa keterangan'));
        }

        return $result;
    }

    private function remember(bool $ok, string $message): void
    {
        Cache::forever(self::STATUS_CACHE, ['state' => $ok, 'message' => $message, 'at' => now()->toDateTimeString()]);
    }
}
