<?php

namespace App\Services;

use App\Jobs\SyncOdReportSheet;
use App\Models\OdActivity;
use App\Models\OdSetting;
use App\Support\OdTeam;
use App\Support\OdWeek;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sinkron satu arah Daily Report → spreadsheet Weekly Report tim OD (Google Sheets), lewat Apps Script yang sama
 * dengan aplikasi OD lama (apps-script.gs di repo od-report). Pekerjaan yang sudah "Simpan Laporan" ditulis ke tab
 * bulan (Nov'26, ...) pada blok POSISI + WEEK; edit memperbarui baris yang sama (dikenali lewat SYNC ID di kolom H)
 * dan hapus mengosongkan barisnya. Baris yang diketik manual di sheet tidak pernah diubah.
 */
class OdSheetSync
{
    /** Nama tab bulan di spreadsheet tim. */
    public const TABS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'AGS', 'Sept', 'Okt', 'Nov', 'Des'];

    /** Apps Script mengunci spreadsheet per permintaan; batch kecil agar tetap di bawah batas waktu. */
    public const CHUNK = 25;

    private const STATUS_KEY = 'od-report:sheet-sync';

    /** Pola URL Web App Apps Script yang diterima. */
    public const WEB_APP_PATTERN = '~^https://script\.google\.com/(a/macros/[\w.-]+/|macros/)s/[\w-]+/exec$~';

    public function enabled(): bool
    {
        return filled($this->webAppUrl()) && filled($this->spreadsheetId());
    }

    public function webAppUrl(): ?string
    {
        return OdSetting::value(OdSetting::SHEET_URL);
    }

    public function spreadsheetId(): ?string
    {
        return self::parseSpreadsheetId(OdSetting::value(OdSetting::SHEET_LINK));
    }

    /** ID spreadsheet dari link lengkap (…/d/<ID>/edit) atau ID saja. */
    public static function parseSpreadsheetId(?string $value): ?string
    {
        $value = trim((string) $value);

        if (preg_match('~/d/([\w-]{20,})~', $value, $match)) {
            return $match[1];
        }

        return preg_match('~^[\w-]{20,}$~', $value) ? $value : null;
    }

    public function spreadsheetUrl(): ?string
    {
        $id = $this->spreadsheetId();

        return $id ? "https://docs.google.com/spreadsheets/d/{$id}/edit" : null;
    }

    /**
     * Cek Web App bisa membuka spreadsheet; mengembalikan nama spreadsheet.
     *
     * @throws RuntimeException
     */
    public function ping(string $url, string $spreadsheetId): string
    {
        try {
            $json = Http::timeout(20)->acceptJson()->get($url, ['action' => 'ping', 'w' => $spreadsheetId])->throw()->json();
        } catch (Throwable $e) {
            throw new RuntimeException('Web App tidak bisa dihubungi: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($json)) {
            throw new RuntimeException('Web App meminta login Google. Deploy ulang Apps Script dengan akses "Siapa saja".');
        }
        if (($json['ok'] ?? false) !== true) {
            throw new RuntimeException($json['error'] ?? 'Web App menolak permintaan.');
        }

        return (string) ($json['weekly'] ?? '');
    }

    public static function key(OdActivity $activity): string
    {
        return 'mw-'.$activity->getKey();
    }

    /**
     * Kirim perubahan di belakang layar (bila sinkron sudah diatur).
     *
     * @param  list<string>  $delete
     */
    public static function queue(array $delete = []): void
    {
        if (app(self::class)->enabled()) {
            SyncOdReportSheet::dispatch($delete)->afterCommit();
        }
    }

    /**
     * Isi baris untuk Apps Script: tab bulan + posisi + nomor week, lalu kolom DETAIL PEKERJAAN..KETERANGAN.
     *
     * @return array{key: string, pos: string, tab: string, wk: int, n: string, r: string, k: string, s: string, ke: string}
     */
    public function entry(OdActivity $activity): array
    {
        $week = OdWeek::of($activity->date);
        [$year, $month] = explode('-', $week->month());

        return [
            'key' => self::key($activity),
            'pos' => mb_strtoupper(OdTeam::positionOf($activity->user)),
            'tab' => self::TABS[(int) $month - 1]."'".substr($year, 2),
            'wk' => $week->number(),
            'n' => $activity->name,
            'r' => OdActivity::WEEKLY_STATUS[$activity->result] ?? $activity->result,
            'k' => (string) $activity->kendala,
            's' => (string) $activity->solusi,
            'ke' => (string) $activity->keterangan,
        ];
    }

    /**
     * Pekerjaan tersimpan yang isinya belum sama dengan yang tertulis di spreadsheet.
     *
     * @return Collection<int, array{activity: OdActivity, entry: array<string, mixed>, hash: string}>
     */
    public function pending(): Collection
    {
        $from = OdSetting::value(OdSetting::SHEET_FROM);

        return OdActivity::query()->reported()->with('user.position')
            ->when(filled($from), fn ($q) => $q->whereDate('date', '>=', $from))
            ->orderBy('date')->orderBy('sort')->orderBy('id')
            ->get()
            ->map(function (OdActivity $activity) {
                $entry = $this->entry($activity);

                return ['activity' => $activity, 'entry' => $entry, 'hash' => sha1(json_encode($entry))];
            })
            ->reject(fn (array $item) => $item['hash'] === $item['activity']->sheet_hash)
            ->values();
    }

    /**
     * Kirim satu batch (plus daftar hapus). Mengembalikan jumlah yang tertulis, terhapus, gagal, dan sisa antrean.
     *
     * @param  list<string>  $delete
     * @return array{written: int, deleted: int, failed: int, remaining: int}
     */
    public function run(array $delete = []): array
    {
        $pending = $this->pending();
        $batch = $pending->take(self::CHUNK);

        if ($batch->isEmpty() && $delete === []) {
            return ['written' => 0, 'deleted' => 0, 'failed' => 0, 'remaining' => 0];
        }

        try {
            $response = $this->send($batch->pluck('entry')->all(), $delete);
        } catch (Throwable $e) {
            $this->remember(false, $e->getMessage());

            throw $e;
        }

        $failed = array_map('strval', (array) ($response['fail'] ?? []));
        foreach ($batch as $item) {
            if (! in_array($item['entry']['key'], $failed, true)) {
                // Lewat query agar tidak memicu event saved (yang akan mengantre sinkron lagi).
                OdActivity::whereKey($item['activity']->getKey())->update(['sheet_hash' => $item['hash']]);
            }
        }

        $result = [
            'written' => (int) ($response['a'] ?? 0),
            'deleted' => (int) ($response['d'] ?? 0),
            'failed' => count($failed),
            'remaining' => $pending->count() - $batch->count(),
        ];

        $message = "{$result['written']} pekerjaan ditulis, {$result['deleted']} dihapus";
        if ($failed) {
            $message .= '. Gagal: '.Str::limit(trim((string) ($response['log'] ?? '')), 200);
        }
        $this->remember($failed === [], $message);

        return $result;
    }

    /**
     * Hasil sinkron terakhir untuk halaman Pengaturan OD.
     *
     * @return array{ok: bool, message: string, at: Carbon}|null
     */
    public function status(): ?array
    {
        $status = Cache::get(self::STATUS_KEY);

        return $status ? [...$status, 'at' => Carbon::parse($status['at'])] : null;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<string>  $delete
     * @return array<string, mixed>
     */
    private function send(array $entries, array $delete): array
    {
        // Apps Script menjawab dengan redirect ke googleusercontent; Http mengikutinya dan membaca JSON-nya.
        $response = Http::timeout(45)->acceptJson()->post($this->webAppUrl(), [
            'stamp' => (string) Str::uuid(),
            'weeklyId' => $this->spreadsheetId(),
            'entries' => $entries,
            'del' => array_values($delete),
        ])->throw();

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Web App Apps Script tidak bisa diakses tanpa login Google. Deploy ulang dengan akses "Siapa saja".');
        }
        if (($json['ok'] ?? false) !== true) {
            throw new RuntimeException('Spreadsheet menolak: '.($json['error'] ?? 'tanpa keterangan'));
        }

        return $json;
    }

    private function remember(bool $ok, string $message): void
    {
        Cache::forever(self::STATUS_KEY, ['ok' => $ok, 'message' => $message, 'at' => now()->toIso8601String()]);
    }
}
