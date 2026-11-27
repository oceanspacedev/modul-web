<?php

namespace App\Services;

use App\Models\OdActivity;
use App\Models\OdDocument;
use App\Models\User;
use App\Support\OdTeam;
use App\Support\OdWeek;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Perhitungan OD Report yang dipakai halaman, teks laporan, dan ekspor: baris Weekly, statistik, dan teks Daily.
 */
class OdReportService
{
    /**
     * Draft dari hari sebelumnya milik user yang bersangkutan dianggap sudah disimpan, seperti di aplikasi lama
     * (dijalankan saat membuka Daily Report atau Dashboard). Draft anggota lain tidak boleh ikut tersimpan.
     */
    public function autoSavePastDrafts(User $user): void
    {
        if (OdActivity::where('user_id', $user->getKey())->whereNull('saved_at')->whereDate('date', '<', today())->update(['saved_at' => now()])) {
            OdSheetSync::queue();
        }
    }

    /**
     * Baris Weekly: aktivitas dengan user + nama pekerjaan + minggu yang sama digabung jadi satu baris. Hasil memakai
     * yang terakhir; kendala/solusi/keterangan memakai isian terakhir yang tidak kosong. Terbaru di atas.
     *
     * @param  Collection<int, OdActivity>  $activities
     * @return Collection<int, object{week: OdWeek, user: User, name: string, result: string, kendala: ?string, solusi: ?string, keterangan: ?string, document: mixed, count: int, last_date: CarbonImmutable}>
     */
    public function weeklyRows(Collection $activities): Collection
    {
        $rows = [];

        foreach ($activities->sortBy([['date', 'asc'], ['id', 'asc']]) as $activity) {
            $week = OdWeek::of($activity->date);
            $key = $activity->user_id.'|'.mb_strtolower(trim($activity->name)).'|'.$week->key();

            if (! isset($rows[$key])) {
                $rows[$key] = (object) [
                    'week' => $week,
                    'user' => $activity->user,
                    'name' => $activity->name,
                    'result' => $activity->result,
                    'kendala' => $activity->kendala,
                    'solusi' => $activity->solusi,
                    'keterangan' => $activity->keterangan,
                    'document' => $activity->document,
                    'count' => 1,
                    'last_date' => CarbonImmutable::parse($activity->date),
                    'last_id' => $activity->id,
                ];

                continue;
            }

            $row = $rows[$key];
            $row->count++;
            $row->result = $activity->result;
            $row->last_date = CarbonImmutable::parse($activity->date);
            $row->last_id = $activity->id;
            $row->document ??= $activity->document;
            if (filled($activity->kendala)) {
                $row->kendala = $activity->kendala;
                $row->solusi = $activity->solusi;
            }
            if (filled($activity->keterangan)) {
                $row->keterangan = $activity->keterangan;
            }
        }

        return collect($rows)->sortBy([
            fn ($a, $b) => strcmp($b->week->key(), $a->week->key()),
            fn ($a, $b) => $b->last_date <=> $a->last_date,
            fn ($a, $b) => $b->last_id <=> $a->last_id,
        ])->values();
    }

    /**
     * @param  Collection<int, OdActivity>  $activities
     * @return array{total: int, done: int, progress: int, hold: int, percent: int}
     */
    public function stats(Collection $activities): array
    {
        $total = $activities->count();
        $done = $activities->where('result', OdActivity::RESULT_DONE)->count();

        return [
            'total' => $total,
            'done' => $done,
            'progress' => $activities->where('result', OdActivity::RESULT_PROGRESS)->count(),
            'hold' => $activities->where('result', OdActivity::RESULT_HOLD)->count(),
            'percent' => $total ? (int) round($done / $total * 100) : 0,
        ];
    }

    /**
     * Ringkasan OD Report untuk bagian OD di Dashboard: angka minggu/bulan ini, dokumen, siapa yang sudah lapor,
     * grafik 30 hari dan aktivitas terbaru. Staff melihat datanya sendiri, Leader seluruh tim.
     *
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $this->autoSavePastDrafts($user);

        $today = CarbonImmutable::today();
        // Bulan tautan report = bulan minggu OdWeek hari ini, sama dengan default halaman Weekly/Monthly.
        $bulan = OdWeek::of($today)->month();
        // Statistik bulan memakai window minggu laporan yang sama dengan halaman Monthly/Weekly, bukan 1–akhir bulan
        // kalender, supaya angka kartu Dashboard bisa dicek ulang di halaman itu.
        $weeks = OdWeek::inMonth($bulan);
        $monthStart = $weeks[0]->start;
        $monthEnd = end($weeks)->end();
        // "Minggu ini" selalu Sabtu–Jumat 7 hari (bukan Week 4 gabungan 14 hari) agar tren dibanding minggu lalu adil.
        $weekStart = $today->subDays(($today->dayOfWeek + 1) % 7);
        $from = $today->subDays(29);

        $activities = OdActivity::query()->visibleTo($user)->reported()
            ->whereDate('date', '>=', $from->min($weekStart->subWeek())->min($monthStart))
            ->get(['id', 'user_id', 'date', 'result']);

        $thisWeek = $activities->filter(fn ($a) => $a->date->betweenIncluded($weekStart, $weekStart->addDays(6)))->count();
        $lastWeek = $activities->filter(fn ($a) => $a->date->betweenIncluded($weekStart->subWeek(), $weekStart->subDay()))->count();
        $month = $this->stats($activities->filter(fn ($a) => $a->date->betweenIncluded($monthStart, $monthEnd)));

        $documents = OdDocument::query()->visibleTo($user)->with('status')->get(['id', 'od_document_status_id']);
        $team = $user->can('manage-od-report') ? OdTeam::members() : collect([$user]);
        $savedToday = OdActivity::reported()->whereDate('date', $today)->distinct()->pluck('user_id');

        return [
            'bulan' => $bulan,
            'thisWeek' => $thisWeek,
            'trend' => $lastWeek ? (int) round(($thisWeek - $lastWeek) / $lastWeek * 100) : ($thisWeek ? 100 : 0),
            'month' => $month,
            'openDocs' => $documents->filter(fn ($d) => $d->status->isOpen())->count(),
            'doneDocs' => $documents->filter(fn ($d) => $d->status->isDone())->count(),
            'reportedToday' => $team->whereIn('id', $savedToday)->count(),
            'teamSize' => $team->count(),
            'attention' => $this->attention($user, $today, $bulan, $month),
            'chart' => collect(range(29, 0))->map(fn ($back) => $today->subDays($back))->map(fn (CarbonImmutable $day) => [
                'label' => $day->format('d/m'),
                'done' => $activities->filter(fn ($a) => $a->date->isSameDay($day) && $a->result === OdActivity::RESULT_DONE)->count(),
                'open' => $activities->filter(fn ($a) => $a->date->isSameDay($day) && $a->result !== OdActivity::RESULT_DONE)->count(),
            ]),
            'recentActivities' => OdActivity::query()->visibleTo($user)->reported()->with(['user', 'document'])
                ->latest('date')->latest('id')->limit(8)->get(),
        ];
    }

    /**
     * Hal yang perlu perhatian (dari aplikasi lama): draft belum disimpan dan laporan hari ini kosong (hanya untuk
     * anggota tim OD, bukan ADMIN yang sekadar bisa membuka OD Report), serta pekerjaan Hold bulan ini.
     *
     * @param  string  $bulan  bulan report (Y-m) yang sedang dipakai kartu Dashboard
     * @param  array{hold: int}  $month
     * @return list<array{0: string, 1: string}>
     */
    private function attention(User $user, CarbonImmutable $today, string $bulan, array $month): array
    {
        $items = [];

        if (OdTeam::query()->whereKey($user->getKey())->exists()) {
            $mineToday = OdActivity::where('user_id', $user->getKey())->whereDate('date', $today)->get(['saved_at']);

            if ($drafts = $mineToday->whereNull('saved_at')->count()) {
                $items[] = ["{$drafts} aktivitas belum disimpan", route('od-report.daily.index')];
            }
            if ($mineToday->isEmpty()) {
                $items[] = ['Laporan hari ini belum diisi', route('od-report.daily.index')];
            }
        }
        if ($month['hold']) {
            $items[] = ["{$month['hold']} pekerjaan berstatus Hold bulan ini", route('od-report.weekly', ['bulan' => $bulan, 'status' => OdActivity::RESULT_HOLD])];
        }

        return $items;
    }

    /**
     * Teks laporan harian siap salin dalam format "Afternoon Briefing BusDev": per orang, nama depan KAPITAL lalu
     * daftar pekerjaannya (Hasil / Kendala / Solusi). Leader mendapat gabungan tim (dirinya dulu), Staff hanya
     * bagiannya sendiri. Orang tanpa pekerjaan hari itu dilewati.
     */
    public function dailyText(User $user, CarbonImmutable $date): string
    {
        $people = collect([$user]);
        if ($user->can('manage-od-report')) {
            $people = $people->merge(OdTeam::query()->whereKeyNot($user->getKey())->get());
        }

        $sections = $people
            ->map(fn (User $person) => [$person, $this->activitiesOn($person, $date)])
            ->filter(fn (array $entry) => $entry[1]->isNotEmpty())
            ->map(fn (array $entry) => mb_strtoupper(strtok($entry[0]->nama_lengkap, ' '))."\n".$this->list($entry[1]));

        $header = "Terlampir\nAfternoon Briefing BusDev\n(".$date->locale('id')->translatedFormat('l, j F Y').')';

        return $header."\n\n".($sections->isEmpty() ? 'Belum ada pekerjaan hari ini.' : $sections->implode("\n\n\n"));
    }

    /** @return Collection<int, OdActivity> */
    private function activitiesOn(User $user, CarbonImmutable $date): Collection
    {
        return OdActivity::where('user_id', $user->getKey())->whereDate('date', $date)->orderBy('sort')->orderBy('id')->get();
    }

    /** @param Collection<int, OdActivity> $activities */
    private function list(Collection $activities): string
    {
        return $activities->values()->map(fn (OdActivity $a, int $i) => ($i + 1).'. '.$a->name
            ."\n   Hasil : {$a->result}"
            ."\n   Kendala : ".($a->kendala ?: '-')
            ."\n   Solusi : ".($a->solusi ?: '-'))->implode("\n\n");
    }
}
