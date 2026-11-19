<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Minggu laporan tim OD, sama dengan file Weekly Report tim: Sabtu–Jumat, dan satu minggu masuk ke bulan hari
 * Rabu-nya (mis. 31 Okt – 6 Nov 2026 = Week 1 November).
 */
final class OdWeek
{
    public const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private function __construct(public readonly CarbonImmutable $start) {}

    public static function of(CarbonInterface|string $date): self
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        // Carbon: Minggu = 0 … Sabtu = 6. Mundur ke Sabtu terdekat.
        return new self($day->subDays(($day->dayOfWeek + 1) % 7));
    }

    public function end(): CarbonImmutable
    {
        return $this->start->addDays(6);
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
        return intdiv($this->wednesday()->day - 1, 7) + 1;
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
            $week = new self($week->start->addWeek());
        }

        $weeks = [];
        while ($week->month() === $month) {
            $weeks[] = $week;
            $week = new self($week->start->addWeek());
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
