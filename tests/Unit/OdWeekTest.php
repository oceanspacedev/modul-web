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
}
