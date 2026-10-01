<?php

namespace App\Helpers;

use DateTime;
use Carbon\Carbon;

class ConvertDate
{

    public static function getMondayOrSaturday($year, $week, $isStart)
    {
        $date = new DateTime();
        $date->setISODate($year, $week);
        $parse = Carbon::parse($date);
        return $isStart ? $parse->startOfWeek() : $parse->endOfWeek();
    }

    public static function getEndOfMonth($year, $week)
    {
        $date = new DateTime();
        $date->setISODate($year, $week);
        return Carbon::parse($date)->endOfMonth();
    }
}
