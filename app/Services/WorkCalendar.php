<?php

namespace App\Services;

use App\Models\CalendarException;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class WorkCalendar
{
    protected Carbon $date;

    /**
     * Базовые фиксированные нерабочие дни РФ (fallback)
     */
    protected static array $fixedHolidays = [
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

    public function __construct(CarbonInterface|string|null $date = null)
    {
        if ($date instanceof CarbonInterface) {
            $this->date = $date->copy();
        } elseif (is_string($date) && !empty($date)) {
            $this->date = Carbon::parse($date);
        } else {
            $this->date = Carbon::now();
        }
    }

    public static function create(mixed $date = null): self
    {
        return new self($date);
    }

    public static function now(): self
    {
        return new self(Carbon::now());
    }

    /**
     * Получить все исключения за год с кэшированием на 24 часа
     */
    protected static function getExceptionsForYear(int $year): array
    {
        return Cache::remember("calendar_exceptions_{$year}", 86400, function () use ($year) {
            if (!Schema::hasTable('calendar_exceptions')) {
                return [];
            }

            return CalendarException::query()
                ->whereYear('date', $year)
                ->pluck('is_workday', 'date')
                ->mapWithKeys(function ($isWorkday, $date) {
                    $key = Carbon::parse($date)->format('Y-m-d');
                    return [$key => (bool) $isWorkday];
                })
                ->toArray();
        });
    }

    /**
     * Проверка: является ли дата рабочим днем
     */
    public static function isWorkDay(CarbonInterface $date): bool
    {
        $ymd = $date->format('Y-m-d');
        $md = $date->format('m-d');
        $year = (int) $date->format('Y');

        $exceptions = self::getExceptionsForYear($year);

        // 1. Если дата явно задана в настройках админки
        if (array_key_exists($ymd, $exceptions)) {
            return (bool) $exceptions[$ymd];
        }

        // 2. Стандартные праздники РФ (если админ еще не переопределил)
        if (in_array($md, self::$fixedHolidays, true)) {
            return false;
        }

        // 3. Выходные дни (суббота, воскресенье)
        if ($date->isWeekend()) {
            return false;
        }

        return true;
    }

    public function addWorkDays(int $days): self
    {
        if ($days <= 0) {
            return $this;
        }

        $added = 0;
        while ($added < $days) {
            $this->date->addDay();
            if (self::isWorkDay($this->date)) {
                $added++;
            }
        }

        return $this;
    }

    public function diffInHours(self|CarbonInterface $target): int
    {
        $carbonTarget = $target instanceof self ? $target->getCarbon() : $target;
        return (int) $this->date->diffInHours($carbonTarget, false);
    }

    public function diffInDays(self|CarbonInterface $target): int
    {
        $carbonTarget = $target instanceof self ? $target->getCarbon() : $target;
        return (int) $this->date->diffInDays($carbonTarget, false);
    }

    public function getCarbon(): Carbon
    {
        return $this->date;
    }

    public function format(string $format): string
    {
        return $this->date->format($format);
    }

    public function __toString(): string
    {
        return $this->date->toDateTimeString();
    }
}