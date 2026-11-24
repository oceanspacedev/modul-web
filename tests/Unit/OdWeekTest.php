<?php

namespace Tests\Unit;

use App\Support\OdWeek;
use PHPUnit\Framework\TestCase;

class OdWeekTest extends TestCase
{
    public function test_week_runs_saturday_to_friday_and_belongs_to_the_month_of_its_wednesday(): void
    {
        foreach (['2026-10-31', '2026-11-03', '2026-11-06'] as $day) {
            $week = OdWeek::of($day);
            $this->assertSame('2026-10-31', $week->key(), $day);
            $this->assertSame('2026-11', $week->month());
            $this->assertSame(1, $week->number());
            $this->assertSame('Week 1 November 2026', $week->label());
        }

        $this->assertSame('2026-11-07', OdWeek::of('2026-11-07')->key());
        $this->assertSame('31 Oktober - 06 November 2026', OdWeek::of('2026-11-01')->range());
        $this->assertSame('07 - 13 November 2026', OdWeek::of('2026-11-10')->range());
    }

    public function test_week_whose_wednesday_falls_in_the_next_month_moves_there(): void
    {
        // 28 Nov - 4 Des 2026: Rabu 2 Desember.
        $week = OdWeek::of('2026-11-29');
        $this->assertSame('2026-12', $week->month());
        $this->assertSame(1, $week->number());
    }

    public function test_weeks_in_month_lists_every_week_of_that_month(): void
    {
        $keys = array_map(fn (OdWeek $w) => $w->key(), OdWeek::inMonth('2026-11'));

        $this->assertSame(['2026-10-31', '2026-11-07', '2026-11-14', '2026-11-21'], $keys);
        $this->assertSame('2026-11-28', OdWeek::inMonth('2026-12')[0]->key());
    }

    public function test_fifth_week_is_merged_into_week_four(): void
    {
        // Desember 2026 punya 5 Rabu; 26 Des - 1 Jan (Rabu 30 Des) ikut Week 4 (19 - 25 Des).
        foreach (['2026-12-19', '2026-12-25', '2026-12-26', '2027-01-01'] as $day) {
            $week = OdWeek::of($day);
            $this->assertSame('2026-12-19', $week->key(), $day);
            $this->assertSame('2026-12', $week->month(), $day);
            $this->assertSame(4, $week->number(), $day);
        }

        $this->assertSame('2027-01-01', OdWeek::of('2026-12-26')->end()->toDateString());
        $this->assertSame('19 Desember - 01 Januari 2027', OdWeek::of('2026-12-30')->range());
        $this->assertSame('2027-01-02', OdWeek::of('2027-01-02')->key());
        $this->assertSame(1, OdWeek::of('2027-01-02')->number());

        $weeks = OdWeek::inMonth('2026-12');
        $this->assertSame(['2026-11-28', '2026-12-05', '2026-12-12', '2026-12-19'], array_map(fn (OdWeek $w) => $w->key(), $weeks));
        $this->assertSame('2027-01-02', OdWeek::inMonth('2027-01')[0]->key());

        // Bulan tanpa minggu ke-5 tidak berubah: Week 4 tetap 7 hari.
        $this->assertSame('2026-11-27', OdWeek::of('2026-11-21')->end()->toDateString());
    }
}
