<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Minggu laporan tim OD, sama dengan file Weekly Report tim: Sabtu–Jumat, dan satu minggu masuk ke bulan hari
 * Rabu-nya (mis. 31 Okt – 6 Nov 2026 = Week 1 November). Satu bulan paling banyak 4 week: minggu ke-5 (Rabu
 * tanggal 29–31) digabung ke Week 4, sehingga Week 4 bulan itu 14 hari (mis. 19 Des 2026 – 1 Jan 2027).
 */
final class OdWeek
{
    public const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** Jumlah week maksimal per bulan, sama dengan blok WEEK di spreadsheet tim. */
    public const MAX_WEEKS = 4;

    private function __construct(public readonly CarbonImmutable $start, private readonly int $days = 7) {}

    public static function of(CarbonInterface|string $date): self
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        // Carbon: Minggu = 0 … Sabtu = 6. Mundur ke Sabtu terdekat.
        $start = $day->subDays(($day->dayOfWeek + 1) % 7);

        // Minggu ke-5 ikut Week 4: mulai dari Sabtu Week 4, dan Week 4 memanjang 14 hari.
        if (self::numberFrom($start) > self::MAX_WEEKS) {
            $start = $start->subWeek();
        }

        return new self($start, self::numberFrom($start->addWeek()) > self::MAX_WEEKS ? 14 : 7);
    }

    /** Nomor week dari tanggal Rabu-nya, sebelum minggu ke-5 digabung. */
    private static function numberFrom(CarbonImmutable $start): int
    {
        return intdiv($start->addDays(4)->day - 1, 7) + 1;
    }

    public function end(): CarbonImmutable
    {
        return $this->start->addDays($this->days - 1);
    }

    public function wednesday(): CarbonImmutable
    {
        return $this->start->addDays(4);
    }

    /** Bulan minggu ini, format Y-m. */
    public function month(): string
    {
        return $this->wednesday()->format('Y-m');
    }

    public function number(): int
    {
        return self::numberFrom($this->start);
    }

    public function key(): string
    {
        return $this->start->toDateString();
    }

    /** "Week 1 November 2026" */
    public function label(): string
    {
        return 'Week '.$this->number().' '.self::monthLabel($this->month());
    }

    /** "31 Oktober - 06 November 2026" untuk judul blok di ekspor Weekly. */
    public function range(): string
    {
        $start = $this->start;
        $end = $this->end();
        $startMonth = self::MONTHS[$start->month - 1];
        $endMonth = self::MONTHS[$end->month - 1];

        return $start->month === $end->month
            ? sprintf('%02d - %02d %s %d', $start->day, $end->day, $endMonth, $end->year)
            : sprintf('%02d %s - %02d %s %d', $start->day, $startMonth, $end->day, $endMonth, $end->year);
    }

    /**
     * Semua minggu yang masuk ke bulan ini, berurutan.
     *
     * @return list<self>
     */
    public static function inMonth(string $month): array
    {
        $first = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $week = self::of($first);
        if ($week->month() < $month) {
            $week = self::of($week->end()->addDay());
        }

        $weeks = [];
        while ($week->month() === $month) {
            $weeks[] = $week;
            $week = self::of($week->end()->addDay());
        }

        return $weeks;
    }

    /** "2026-11" → "November 2026" */
    public static function monthLabel(string $month): string
    {
        [$year, $number] = explode('-', $month);

        return self::MONTHS[(int) $number - 1].' '.$year;
    }
}
