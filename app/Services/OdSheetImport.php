<?php

namespace App\Services;

use App\Models\DocumentCategory;
use App\Models\OdActivity;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use App\Models\OdSetting;
use App\Models\User;
use App\Support\OdTeam;
use App\Support\OdWeek;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Import satu arah Google Sheets → OD Report, dijalankan Leader dari Pengaturan OD (lewat job ImportOdSheet).
 *
 * - Weekly: spreadsheet Weekly Report tim (tab bulan Jan'26, Feb'26, …, blok POSISI + WEEK) → pekerjaan tersimpan
 *   (od_activities). Tanggalnya hari Senin di week itu; user diambil dari POSISI (otomatis lewat jabatan atau akses
 *   OD, atau pilihan Leader di Pengaturan).
 * - Approval: daftar dokumen (Tanggal, Dokumen/Judul, Status, Catatan, …) → dokumen manual di Approval Dokumen.
 *   Jenis dan badan usaha diambil dari kolomnya, atau dari judul (kode -SOP-/-IK-, [CV CS], awalan MSI-001-).
 *
 * Sheet dibaca lewat export CSV publik, jadi aksesnya harus "Siapa saja yang memiliki link". Setiap baris punya
 * import_key, sehingga import ulang memperbarui baris yang sama; baris yang isinya di sheet tidak berubah tidak
 * menimpa perubahan yang dibuat di aplikasi.
 */
class OdSheetImport
{
    public const WEEKLY = 'weekly';

    public const APPROVAL = 'approval';

    public const TYPES = [self::WEEKLY => 'Weekly Report', self::APPROVAL => 'Approval Dokumen'];

    /** Bulan awal bawaan import Weekly. */
    public const DEFAULT_FROM = '2026-01';

    /** STATUS di Weekly Report tim → hasil pekerjaan. Kosong dianggap masih berjalan. */
    public const WEEKLY_RESULTS = [
        'closed' => OdActivity::RESULT_DONE,
        'done' => OdActivity::RESULT_DONE,
        'selesai' => OdActivity::RESULT_DONE,
        'on progress' => OdActivity::RESULT_PROGRESS,
        'open' => OdActivity::RESULT_HOLD,
        'hold' => OdActivity::RESULT_HOLD,
    ];

    /** Teks status dokumen di sheet → tipe status OD. Selain ini (termasuk kosong) = berjalan. */
    public const DOCUMENT_STATUS_TYPES = [
        'selesai' => OdDocumentStatus::TYPE_DONE,
        'release' => OdDocumentStatus::TYPE_DONE,
        'released' => OdDocumentStatus::TYPE_DONE,
        'rilis' => OdDocumentStatus::TYPE_DONE,
        'done' => OdDocumentStatus::TYPE_DONE,
        'closed' => OdDocumentStatus::TYPE_DONE,
        'batal' => OdDocumentStatus::TYPE_CANCEL,
        'dibatalkan' => OdDocumentStatus::TYPE_CANCEL,
        'cancel' => OdDocumentStatus::TYPE_CANCEL,
        'cancelled' => OdDocumentStatus::TYPE_CANCEL,
    ];

    /** Penulisan badan usaha di sheet (isi [..] di judul atau awalan kode dokumen) → nama baku. */
    public const BADAN_USAHA_ALIASES = [
        'CS' => 'CV CS',
        'CV CS' => 'CV CS',
        'CS AUTO EV' => 'CV CS AUTO EV',
        'CV CS AUTO EV' => 'CV CS AUTO EV',
        'TOP' => 'CV TOP',
        'CV TOP' => 'CV TOP',
        'MSI' => 'PT MSI',
        'PT MSI' => 'PT MSI',
        'SMI' => 'PT SMI',
        'PT SMI' => 'PT SMI',
        'CSN' => 'PT CSN',
        'PT CSN' => 'PT CSN',
        'RISM' => 'RISM',
        'MT' => 'CV MAJU',
        'MAJU' => 'CV MAJU',
        'CV MAJU' => 'CV MAJU',
        'CV MAJU TECNOLOGI' => 'CV MAJU',
        'CV MAJU TEKNOLOGI' => 'CV MAJU',
    ];

    /** Frasa di judul yang menandai jenis dokumen (dipakai bila kodenya ada di Kategori Dokumen). */
    private const JENIS_PHRASES = [
        'SURAT EDARAN' => 'SE',
        'INSTRUKSI KERJA' => 'IK',
        'MEMO INTERNAL' => 'MEMO',
        'JOBDESK' => 'JD',
        'JOB DESCRIPTION' => 'JD',
    ];

    /** Judul kolom di sheet Approval Dokumen → field. Kolom tanpa judul ikut ke catatan. */
    private const DOCUMENT_HEADERS = [
        'dokumen' => 'title',
        'judul' => 'title',
        'judul dokumen' => 'title',
        'nama dokumen' => 'title',
        'tanggal' => 'submitted',
        'tgl' => 'submitted',
        'tanggal pengajuan' => 'submitted',
        'tanggal penyerahan' => 'submitted',
        'tanggal diterima' => 'received',
        'tanggal selesai' => 'received',
        'status' => 'status',
        'jenis' => 'jenis',
        'jenis dokumen' => 'jenis',
        'badan usaha' => 'badan_usaha',
        'pic' => 'pic',
        'catatan' => 'catatan',
        'keterangan' => 'catatan',
    ];

    private const STATUS_KEY = 'od-report:sheet-import:';

    /** Nama tab yang pasti tidak ada: Google mengembalikan tab lain untuk nama yang tidak dikenal. */
    private const MISSING_TAB = '__tab_tidak_ada__';

    /** ID spreadsheet dan gid tab dari link (…/d/<ID>/edit#gid=<gid>). */
    public static function parseLink(?string $link): array
    {
        $gid = preg_match('~[#?&]gid=(\d+)~', (string) $link, $match) ? $match[1] : null;

        return [OdSheetSync::parseSpreadsheetId($link), $gid];
    }

    public static function linkSetting(string $type): string
    {
        return $type === self::WEEKLY ? OdSetting::IMPORT_WEEKLY_LINK : OdSetting::IMPORT_APPROVAL_LINK;
    }

    /**
     * Pilihan POSISI (huruf besar) → user_id yang diatur Leader. Posisi yang pernah ditemukan tanpa pilihan bernilai
     * null (dicocokkan otomatis), supaya tetap muncul di Pengaturan.
     *
     * @return array<string, int|null>
     */
    public static function positionMap(): array
    {
        $map = json_decode((string) OdSetting::value(OdSetting::IMPORT_POSITIONS), true);

        return is_array($map) ? $map : [];
    }

    /** @param array<string, int|null> $map */
    public static function savePositionMap(array $map): void
    {
        ksort($map);
        OdSetting::put([OdSetting::IMPORT_POSITIONS => $map ? json_encode($map) : null]);
    }

    /**
     * Hasil import terakhir untuk halaman Pengaturan OD.
     *
     * @return array{ok: bool, message: string, at: Carbon}|null
     */
    public function status(string $type): ?array
    {
        $status = Cache::get(self::STATUS_KEY.$type);

        return $status ? [...$status, 'at' => Carbon::parse($status['at'])] : null;
    }

    public function remember(string $type, bool $ok, string $message): void
    {
        Cache::forever(self::STATUS_KEY.$type, ['ok' => $ok, 'message' => $message, 'at' => now()->toIso8601String()]);
    }

    /**
     * Isi satu tab sebagai baris-baris sel. Dengan $sheet, tab dibaca berdasarkan nama (termasuk tab tersembunyi);
     * tanpa itu lewat gid (kosong = tab pertama).
     *
     * @return list<list<string>>
     *
     * @throws RuntimeException
     */
    public function fetch(string $spreadsheetId, ?string $sheet = null, ?string $gid = null): array
    {
        $base = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/";

        try {
            $response = $sheet !== null
                ? Http::timeout(60)->get($base.'gviz/tq', ['tqx' => 'out:csv', 'sheet' => $sheet, 'headers' => 1])
                : Http::timeout(60)->get($base.'export', array_filter(['format' => 'csv', 'gid' => $gid]));
        } catch (Throwable $e) {
            throw new RuntimeException('Google Sheets tidak bisa dihubungi: '.$e->getMessage(), previous: $e);
        }

        if ($response->status() === 404) {
            throw new RuntimeException('Spreadsheet tidak ditemukan. Periksa link-nya.');
        }
        if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'text/csv')) {
            throw new RuntimeException('Spreadsheet tidak bisa dibaca. Ubah aksesnya menjadi "Siapa saja yang memiliki link".');
        }

        return self::parseCsv($response->body());
    }

    /**
     * @return list<list<string>>
     */
    public static function parseCsv(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $rows[] = array_map(fn ($cell) => trim((string) $cell), $row);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Import tab bulan Weekly Report mulai $fromMonth (Y-m) sampai bulan ini.
     *
     * @return array{created: int, updated: int, unchanged: int, months: list<string>, missing: list<string>, unmapped: array<string, int>, errors: list<string>}
     */
    public function importWeekly(string $spreadsheetId, string $fromMonth): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'months' => [], 'missing' => [], 'unmapped' => [], 'errors' => []];
        $fallback = $this->fetch($spreadsheetId, self::MISSING_TAB);
        $resolve = $this->positionResolver();
        $seen = [];

        $month = CarbonImmutable::createFromFormat('!Y-m', $fromMonth);
        $last = OdWeek::of(today())->month();

        for (; $month->format('Y-m') <= $last; $month = $month->addMonth()) {
            $tab = OdSheetSync::TABS[$month->month - 1]."'".$month->format('y');
            $rows = $this->fetch($spreadsheetId, $tab);
            $items = $rows === $fallback ? null : self::weeklyItems($rows);

            if ($items === null) {
                $result['missing'][] = $tab;

                continue;
            }

            $result['months'][] = $tab;
            foreach ($items as $item) {
                $seen[$item['pos']] = true;
            }
            $this->saveActivities($month->format('Y-m'), $items, $resolve, $result);
        }

        // Posisi yang ditemukan ikut tersimpan (null = otomatis) agar tampil di Pengaturan dan bisa diganti.
        $map = self::positionMap();
        foreach (array_keys($seen) as $position) {
            $map[$position] ??= null;
        }
        self::savePositionMap($map);

        return $result;
    }

    /**
     * Baris pekerjaan dari satu tab bulan; null bila tab tidak berformat Weekly Report (POSISI, WEEK, DETAIL …).
     *
     * @param  list<list<string>>  $rows
     * @return list<array{pos: string, week: int, name: string, status: string, kendala: string, solusi: string, keterangan: string, line: int}>|null
     */
    public static function weeklyItems(array $rows): ?array
    {
        $start = null;
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            if (Str::upper($row[0] ?? '') === 'POSISI' && Str::contains(Str::upper($row[2] ?? ''), 'DETAIL')) {
                $start = $index + 1;
                break;
            }
        }
        if ($start === null) {
            return null;
        }

        $items = [];
        $position = null;
        $week = null;

        foreach (array_slice($rows, $start, null, true) as $index => $row) {
            [$pos, $wk, $name, $status, $kendala, $solusi, $keterangan] = array_pad($row, 7, '');

            if ($pos !== '') {
                $position = Str::upper(preg_replace('/\s+/', ' ', $pos));
                $week = null;
            }
            if ($wk !== '' && is_numeric($wk)) {
                $week = max(1, min(OdWeek::MAX_WEEKS, (int) $wk));
            }

            if ($name === '') {
                // Sel DETAIL yang di-merge dua baris: statusnya ada di baris kedua.
                $previous = array_key_last($items);
                if ($previous !== null && $status !== '' && $items[$previous]['status'] === '') {
                    $items[$previous]['status'] = $status;
                }

                continue;
            }
            if ($position === null || $week === null) {
                continue;
            }

            $items[] = [
                'pos' => $position, 'week' => $week, 'name' => $name, 'status' => $status,
                'kendala' => $kendala, 'solusi' => $solusi, 'keterangan' => $keterangan, 'line' => $index + 1,
            ];
        }

        return $items;
    }

    public static function weeklyResult(string $status): string
    {
        return self::WEEKLY_RESULTS[Str::lower($status)] ?? OdActivity::RESULT_PROGRESS;
    }

    /**
     * POSISI → anggota tim: pilihan di Pengaturan, selain itu dicocokkan otomatis (autoMatch).
     *
     * @return \Closure(string): ?User
     */
    private function positionResolver(): \Closure
    {
        $members = OdTeam::members()->keyBy('id');
        $chosen = self::positionMap();
        $cache = [];

        return function (string $position) use ($members, $chosen, &$cache): ?User {
            if (! array_key_exists($position, $cache)) {
                $userId = $chosen[$position] ?? null;
                $cache[$position] = $userId && $members->has($userId) ? $members[$userId] : self::autoMatch($position, $members);
            }

            return $cache[$position];
        };
    }

    /**
     * Anggota tim untuk POSISI tanpa pilihan manual: yang jabatannya sama persis, selain itu menurut akses OD
     * (POSISI berisi LEADER → OD Leader, STAFF → OD Staff). Hanya bila tepat satu orang yang cocok.
     *
     * @param  Collection<int, User>  $members
     */
    public static function autoMatch(string $position, Collection $members): ?User
    {
        $only = fn (Collection $users) => $users->count() === 1 ? $users->first() : null;

        $exact = $members->filter(fn (User $user) => Str::upper(OdTeam::positionOf($user)) === $position);
        if ($exact->isNotEmpty()) {
            return $only($exact);
        }

        [$leaders, $staff] = $members->partition(fn (User $user) => $user->can('manage-od-report'));

        return match (true) {
            Str::contains($position, 'LEADER') => $only($leaders),
            Str::contains($position, 'STAFF') => $only($staff),
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  \Closure(string): ?User  $resolve
     * @param  array<string, mixed>  $result
     */
    private function saveActivities(string $month, array $items, \Closure $resolve, array &$result): void
    {
        $weeks = OdWeek::inMonth($month);
        $sync = app(OdSheetSync::class);
        $occurrence = [];
        $sort = [];
        $rows = [];

        foreach ($items as $item) {
            $user = $resolve($item['pos']);
            if (! $user) {
                $result['unmapped'][$item['pos']] = ($result['unmapped'][$item['pos']] ?? 0) + 1;

                continue;
            }

            $week = $weeks[min($item['week'], count($weeks)) - 1];
            $base = sha1(implode('|', [$month, $item['pos'], $item['week'], Str::lower($item['name'])]));
            $occurrence[$base] = ($occurrence[$base] ?? 0) + 1;
            $date = $week->start->addDays(2)->toDateString(); // Senin

            $values = [
                'user_id' => $user->getKey(),
                'date' => $date,
                'name' => Str::limit($item['name'], 197),
                'result' => self::weeklyResult($item['status']),
                'kendala' => self::limitOrNull($item['kendala']),
                'solusi' => self::limitOrNull($item['solusi']),
                'keterangan' => self::limitOrNull($item['keterangan']),
            ];
            $rows["act:{$base}:{$occurrence[$base]}"] = [
                'values' => $values,
                'user' => $user,
                'sort' => $sort[$user->getKey().$date] = ($sort[$user->getKey().$date] ?? 0) + 1,
                'hash' => sha1(json_encode($values)),
            ];
        }

        $existing = OdActivity::whereIn('import_key', array_keys($rows))->get()->keyBy('import_key');

        foreach ($rows as $key => $row) {
            $activity = $existing->get($key);
            if ($activity?->import_hash === $row['hash']) {
                $result['unchanged']++;

                continue;
            }

            $activity ??= new OdActivity(['import_key' => $key, 'saved_at' => now(), 'sort' => $row['sort']]);
            $isNew = ! $activity->exists;
            $activity->fill([...$row['values'], 'import_hash' => $row['hash']]);

            // Tanpa event saved: pekerjaan lama ini berasal dari spreadsheet, jadi tidak dikirim balik ke sana.
            $activity->saveQuietly();
            $activity->setRelation('user', $row['user']);
            $activity->sheet_hash = sha1(json_encode($sync->entry($activity)));
            $activity->saveQuietly();

            $result[$isNew ? 'created' : 'updated']++;
        }
    }

    /**
     * Import tab daftar dokumen (gid kosong = tab pertama). Dokumen dibuat sebagai dokumen manual atas nama $by.
     *
     * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: list<string>}
     */
    public function importApproval(string $spreadsheetId, ?string $gid, User $by): array
    {
        $rows = $this->fetch($spreadsheetId, gid: $gid);
        $documents = self::documentItems($rows);
        if ($documents === null) {
            throw new RuntimeException('Kolom Dokumen/Judul tidak ditemukan di baris judul sheet.');
        }

        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => []];
        $statuses = OdDocumentStatus::ordered()->get();
        $codes = DocumentCategory::pluck('code')->all();
        $members = OdTeam::members();
        $occurrence = [];
        $previousDate = null;
        $prepared = [];

        foreach ($documents as $item) {
            $submitted = self::parseDate($item['submitted']) ?? $previousDate;
            if (! $submitted) {
                $result['skipped']++;
                $result['errors'][] = "Baris {$item['line']}: tanggal kosong, dilewati.";

                continue;
            }
            $previousDate = $submitted;

            $status = self::documentStatus($item['status'], $statuses);
            $done = $status->isDone();
            $pic = self::matchMember($item['pic'], $members) ?? $by;

            $values = [
                'title' => Str::limit($item['title'], 247),
                'jenis' => self::jenis($item['jenis'], $item['title'], $codes),
                'badan_usaha' => Str::limit(self::badanUsaha($item['badan_usaha'], $item['title']), 117),
                'od_document_status_id' => $status->getKey(),
                'pic_user_id' => $pic->getKey(),
                'catatan' => self::limitOrNull($item['catatan']),
                'submitted_at' => $submitted->toDateString(),
                'received_at' => $done ? self::parseDate($item['received'])?->toDateString() : null,
                'month' => $submitted->format('Y-m'),
            ];

            $base = sha1(Str::lower(preg_replace('/\s+/', ' ', $item['title'])));
            $occurrence[$base] = ($occurrence[$base] ?? 0) + 1;
            $prepared["doc:{$base}:{$occurrence[$base]}"] = ['values' => $values, 'done' => $done, 'hash' => sha1(json_encode($values))];
        }

        $existing = OdDocument::withTrashed()->whereIn('import_key', array_keys($prepared))->get()->keyBy('import_key');

        foreach ($prepared as $key => $row) {
            $document = $existing->get($key);
            // Dokumen impor yang sudah dihapus di aplikasi tidak dihidupkan lagi.
            if ($document?->import_hash === $row['hash'] || $document?->trashed()) {
                $result['unchanged']++;

                continue;
            }

            $document ??= new OdDocument(['import_key' => $key]);
            $isNew = ! $document->exists;
            $values = $row['values'];
            // Sheet tanpa kolom tanggal selesai: tanggal selesai yang sudah tercatat di aplikasi dipertahankan.
            if ($row['done'] && $values['received_at'] === null) {
                $values['received_at'] = $document->received_at?->toDateString();
            }
            $document->fill([...$values, 'import_hash' => $row['hash']])->save();

            $result[$isNew ? 'created' : 'updated']++;
        }

        return $result;
    }

    /**
     * Baris dokumen dari tab daftar dokumen; null bila kolom judul dokumen tidak ditemukan.
     *
     * @param  list<list<string>>  $rows
     * @return list<array{title: string, submitted: string, received: string, status: string, jenis: string, badan_usaha: string, pic: string, catatan: string, line: int}>|null
     */
    public static function documentItems(array $rows): ?array
    {
        $columns = null;
        $start = 0;
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $fields = array_map(fn ($cell) => self::DOCUMENT_HEADERS[Str::lower(preg_replace('/\s+/', ' ', $cell))] ?? ($cell === '' ? 'extra' : null), $row);
            if (in_array('title', $fields, true)) {
                $columns = $fields;
                $start = $index + 1;
                break;
            }
        }
        if ($columns === null) {
            return null;
        }

        $items = [];
        foreach (array_slice($rows, $start, null, true) as $index => $row) {
            $item = ['title' => '', 'submitted' => '', 'received' => '', 'status' => '', 'jenis' => '', 'badan_usaha' => '', 'pic' => '', 'catatan' => '', 'line' => $index + 1];
            $notes = [];

            foreach ($row as $column => $cell) {
                $field = $columns[$column] ?? 'extra';
                // Kolom tanpa judul berisi angka saja = nomor urut, bukan catatan.
                if ($cell === '' || $field === null || ($field === 'extra' && is_numeric($cell))) {
                    continue;
                }
                if (in_array($field, ['catatan', 'extra'], true)) {
                    $notes[] = $cell;
                } elseif ($item[$field] === '') {
                    $item[$field] = $cell;
                }
            }

            if ($item['title'] === '') {
                continue;
            }
            $item['title'] = preg_replace('/\s+/', ' ', $item['title']);
            $item['catatan'] = preg_replace('/\s+/', ' ', implode(' · ', $notes));
            $items[] = $item;
        }

        return $items;
    }

    /** @param EloquentCollection<int, OdDocumentStatus> $statuses */
    public static function documentStatus(string $text, EloquentCollection $statuses): OdDocumentStatus
    {
        $text = Str::lower(trim($text));

        $named = $statuses->first(fn (OdDocumentStatus $status) => Str::lower($status->name) === $text);
        $type = self::DOCUMENT_STATUS_TYPES[$text] ?? OdDocumentStatus::TYPE_OPEN;

        return $named ?? $statuses->firstWhere('type', $type) ?? $statuses->firstWhere('type', OdDocumentStatus::TYPE_OPEN);
    }

    /**
     * Kode Kategori Dokumen: kolom Jenis bila kodenya dikenal, selain itu kode atau frasa yang paling awal muncul di
     * judul (001-SOP-…, MSI-001-IK-…, MEMO INTERNAL …), cadangannya "Dokumen Lainnya".
     *
     * @param  list<string>  $codes
     */
    public static function jenis(string $column, string $title, array $codes): string
    {
        $byUpper = collect($codes)->keyBy(fn ($code) => Str::upper($code));
        if ($column !== '' && $byUpper->has(Str::upper($column))) {
            return $byUpper[Str::upper($column)];
        }

        $title = Str::upper($title);
        $candidates = [];
        foreach ($byUpper as $upper => $code) {
            if (preg_match('/^[A-Z0-9]+$/', $upper) && preg_match('/(?<![A-Z0-9])'.$upper.'(?![A-Z0-9])/', $title, $m, PREG_OFFSET_CAPTURE)) {
                $candidates[$m[0][1]] = $code;
            }
        }
        foreach (self::JENIS_PHRASES as $phrase => $code) {
            if ($byUpper->has($code) && ($position = strpos($title, $phrase)) !== false) {
                $candidates[$position] ??= $byUpper[$code];
            }
        }
        ksort($candidates);

        return $candidates ? reset($candidates) : DocumentCategory::FALLBACK_CODE;
    }

    /** Kolom Badan Usaha, isi [...] di judul, atau awalan kode dokumen (MSI-001-…); kosong = "-". */
    public static function badanUsaha(string $column, string $title): string
    {
        $value = $column;
        if ($value === '' && preg_match('/\[([^\[\]]+)\]?[^\[]*$/', $title, $match)) {
            $value = $match[1];
        }
        if ($value === '' && preg_match('/^\s*([A-Za-z]{2,5})-\d{2,4}-/', $title, $match)) {
            $value = $match[1];
        }

        $value = Str::upper(trim(preg_replace('/\s+/', ' ', $value)));

        return $value === '' ? '-' : (self::BADAN_USAHA_ALIASES[$value] ?? $value);
    }

    public static function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        foreach (['!d/m/Y', '!j/n/Y', '!Y-m-d', '!d-m-Y', '!d/m/y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);
            } catch (Throwable) {
                continue;
            }
            if ($date && $date->format(ltrim($format, '!')) === $value) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Anggota tim yang namanya cocok dengan isi kolom PIC.
     *
     * @param  EloquentCollection<int, User>|Collection<int, User>  $members
     */
    private static function matchMember(string $name, Collection $members): ?User
    {
        $name = Str::lower(trim($name));
        if ($name === '') {
            return null;
        }

        return $members->first(fn (User $user) => Str::lower((string) $user->nama_lengkap) === $name)
            ?? $members->first(fn (User $user) => Str::contains(Str::lower((string) $user->nama_lengkap), $name));
    }

    private static function limitOrNull(string $value): ?string
    {
        return $value === '' ? null : Str::limit($value, 297);
    }
}
