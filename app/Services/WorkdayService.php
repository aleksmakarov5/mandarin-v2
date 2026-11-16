<?php

namespace App\Services;

use Carbon\Carbon;

class WorkdayService
{
    /**
     * Официальные праздничные дни РФ (месяц-день)
     */
    protected static array $holidays = [
        '01-01',
        '01-02',
        '01-03',
        '01-04',
        '01-05',
        '01-06',
        '01-07',
        '01-08',
        '02-23',
        '03-08',
        '05-01',
        '05-09',
        '06-12',
        '11-04',
    ];

    /**
     * Подсчет количества рабочих дней между двумя датами
     */
    public static function getWorkdaysBetween(Carbon $start, Carbon $end): int
    {
        if ($start->gt($end)) {
            return 0;
        }

        $workdays = 0;
        $current = $start->copy()->startOfDay();
        $target = $end->copy()->startOfDay();

        while ($current->lte($target)) {
            if (self::isWorkingDay($current)) {
                $workdays++;
            }
            $current->addDay();
        }

        return $workdays;
    }

    /**
     * Проверка: является ли дата рабочим днем
     */
    public static function isWorkingDay(Carbon $date): bool
    {
        // Суббота и Воскресенье - выходные
        if ($date->isWeekend()) {
            return false;
        }

        // Проверка праздников РФ
        if (in_array($date->format('m-d'), self::$holidays, true)) {
            return false;
        }

        return true;
    }
}