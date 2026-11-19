<?php

namespace App\Services;

use App\Models\OdActivity;
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
     * Draft dari hari sebelumnya dianggap sudah disimpan, seperti di aplikasi lama (dijalankan saat membuka OD Report).
     */
    public function autoSavePastDrafts(): void
    {
        OdActivity::whereNull('saved_at')->whereDate('date', '<', today())->update(['saved_at' => now()]);
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
     * Teks laporan harian siap salin. Leader mendapat format "Afternoon Briefing BusDev" gabungan tim: bagiannya
     * sendiri diawali nama depan, lalu pekerjaan anggota lain hari itu tanpa nama, nama pekerjaan KAPITAL.
     */
    public function dailyText(User $user, CarbonImmutable $date): string
    {
        $dateLabel = $date->locale('id')->translatedFormat('l, d F Y');
        $mine = $this->activitiesOn($user, $date);

        if (! $user->can('manage-od-report')) {
            return "DAILY REPORT · {$dateLabel}\n{$user->nama_lengkap}\n\n".$this->list($mine, false);
        }

        $others = OdTeam::query()->whereKeyNot($user->getKey())->get()
            ->map(fn (User $member) => $this->activitiesOn($member, $date))
            ->filter(fn (Collection $list) => $list->isNotEmpty());

        $text = "Afternoon Briefing BusDev\n({$dateLabel})\n\n".strtok($user->nama_lengkap, ' ')."\n".$this->list($mine, true);
        foreach ($others as $list) {
            $text .= "\n\n".$this->list($list, true);
        }

        return $text;
    }

    /** @return Collection<int, OdActivity> */
    private function activitiesOn(User $user, CarbonImmutable $date): Collection
    {
        return OdActivity::where('user_id', $user->getKey())->whereDate('date', $date)->orderBy('sort')->orderBy('id')->get();
    }

    /** @param Collection<int, OdActivity> $activities */
    private function list(Collection $activities, bool $upper): string
    {
        return $activities->values()->map(fn (OdActivity $a, int $i) => ($i + 1).'. '.($upper ? mb_strtoupper($a->name) : $a->name)
            ."\n   Hasil : {$a->result}"
            ."\n   Kendala : ".($a->kendala ?: '-')
            ."\n   Solusi : ".($a->solusi ?: '-'))->implode("\n\n");
    }
}
