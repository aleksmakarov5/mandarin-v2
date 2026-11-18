<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplicationTimerService
{
    /**
     * Расчёт таймеров заявки
     *
     * @param object|array $app Заявка
     * @return array{app_timer: array, finance_timer: array}
     */
    public function calculate(object|array $app): array
    {
        $app = (object) $app;

        $tz = 'Europe/Moscow';
        $current = Carbon::now($tz);
        $nowTs = $current->timestamp;

        // Безопасный парсинг даты
        $parseDate = function ($rawDate) use ($tz, $current): Carbon {
            if (empty($rawDate)) {
                return $current->copy();
            }

            if ($rawDate instanceof DateTimeInterface) {
                $str = $rawDate->format('Y-m-d H:i:s');
            } else {
                $str = (string) $rawDate;
            }

            $cleanStr = substr(trim($str), 0, 19);

            try {
                return Carbon::createFromFormat('Y-m-d H:i:s', $cleanStr, $tz) ?: Carbon::parse($cleanStr, $tz);
            } catch (\Throwable) {
                return Carbon::parse($cleanStr, $tz);
            }
        };

        // -------------------------------------------------------------
        // 1. ОСНОВНОЙ СТАТУС ДЕЛА
        // -------------------------------------------------------------
        $idStatus = (int) ($app->id_status ?? $app->status_id ?? 0);

        $statusObj = $app->status ?? null;
        if (!$statusObj && $idStatus > 0 && Schema::hasTable('application_statuses')) {
            $statusObj = DB::table('application_statuses')->where('id', $idStatus)->first();
        }

        $timeDays = (int) ($app->time ?? $statusObj->time ?? 0);

        $rawMaxHours = 0;
        $rawCountHours = 0;

        if ($idStatus < 11) {
            $start = $parseDate($app->created_at ?? null);
            $endDate = WorkCalendar::create($start)->addWorkDays($timeDays)->getCarbon();

            $rawMaxHours = (int) ceil(($endDate->timestamp - $start->timestamp) / 3600);
            $diffSeconds = $endDate->timestamp - $nowTs;
            $rawCountHours = (int) ($diffSeconds >= 0 ? ceil($diffSeconds / 3600) : floor($diffSeconds / 3600));

        } elseif ($idStatus === 11) {
            $start = $parseDate($app->check_date ?: ($app->created_at ?? null));
            $endDate = WorkCalendar::create($start)->addWorkDays($timeDays)->getCarbon();

            $rawMaxHours = (int) ceil(($endDate->timestamp - $start->timestamp) / 3600);
            $diffSeconds = $endDate->timestamp - $nowTs;
            $rawCountHours = (int) ($diffSeconds >= 0 ? ceil($diffSeconds / 3600) : floor($diffSeconds / 3600));

        } elseif ($timeDays !== 0) {
            $start = $parseDate($app->check_date ?: ($app->created_at ?? null));
            $endDate = WorkCalendar::create($start)->addWorkDays($timeDays)->getCarbon();
            $diffSeconds = $endDate->timestamp - $nowTs;

            $rawMaxHours = (int) ceil(($endDate->timestamp - $start->timestamp) / 3600);
            $rawCountHours = (int) ($diffSeconds >= 0 ? ceil($diffSeconds / 3600) : floor($diffSeconds / 3600));

        } else {
            // Динамические статусы (time == 0)
            if ($idStatus === 17) {
                $start = $parseDate($app->start_date ?: ($app->napr_date ?: ($app->created_at ?? null)));
                $endDate = $app->end_date ? $parseDate($app->end_date) : $start;

                $rawMaxHours = (int) ceil(($endDate->timestamp - $start->timestamp) / 3600);
                $diffSeconds = $endDate->timestamp - $nowTs;
                $rawCountHours = (int) ($diffSeconds >= 0 ? ceil($diffSeconds / 3600) : floor($diffSeconds / 3600));

            } elseif ($idStatus === 13) {
                $start = $parseDate($app->check_date ?: ($app->created_at ?? null));
                $view = $app->view_date ? $parseDate($app->view_date) : $start;
                $diffSeconds = $view->timestamp - $nowTs;

                $rawMaxHours = (int) ceil(($view->timestamp - $start->timestamp) / 3600);
                $rawCountHours = (int) ($diffSeconds >= 0 ? ceil($diffSeconds / 3600) : floor($diffSeconds / 3600));

            } elseif ($idStatus === 15) {
                $start = $parseDate($app->check_date ?: ($app->created_at ?? null));
                $sp = $app->sp_date ? $parseDate($app->sp_date) : $start;

                $rawMaxHours = (int) ceil(($sp->timestamp - $start->timestamp) / 3600);
                $diffSeconds = $sp->timestamp - $nowTs;
                $rawCountHours = (int) ($diffSeconds >= 0 ? ceil($diffSeconds / 3600) : floor($diffSeconds / 3600));
            }
        }

        $appTimerData = $this->transformHoursToOutput($rawCountHours, $rawMaxHours, $idStatus);

        // -------------------------------------------------------------
        // 2. ФИНАНСОВЫЙ СТАТУС (max_hour_f / count_hour_f)
        // -------------------------------------------------------------
        $finStatusId = (int) ($app->finance_status_id ?? $app->id_finance_status ?? 0);

        $finStatusObj = $app->financeStatus ?? $app->finance_status ?? null;
        if (!$finStatusObj && $finStatusId > 1 && Schema::hasTable('finance_statuses')) {
            $finStatusObj = DB::table('finance_statuses')->where('id', $finStatusId)->first();
        }

        $finTimeDays = (int) (
            $app->fin_time ??
            $app->finance_time ??
            $finStatusObj->time ??
            $finStatusObj->fin_time ??
            0
        );

        $rawMaxHoursF = 0;
        $rawCountHoursF = 0;

        if ($finStatusId > 1 && $finTimeDays > 0) {
            $rawFinStart = $app->ic_answer_date ?: ($app->napr_date ?: ($app->check_date ?: ($app->updated_at ?? null)));
            $finStart = $parseDate($rawFinStart);
            $finEnd = WorkCalendar::create($finStart)->addWorkDays($finTimeDays)->getCarbon();

            $rawMaxHoursF = (int) max(1, ceil(($finEnd->timestamp - $finStart->timestamp) / 3600));
            $diffSec = $finEnd->timestamp - $nowTs;
            $rawCountHoursF = (int) ($diffSec >= 0 ? ceil($diffSec / 3600) : floor($diffSec / 3600));
        }

        $finTimerData = $this->transformHoursToOutput($rawCountHoursF, $rawMaxHoursF, $finStatusId, true);

        return [
            'app_timer' => [
                'has_timer' => $rawMaxHours > 0,
                'max_hour' => $appTimerData['max'],       // отдаем уже поделенное: например, 9 (дней) или 18 (часов)
                'count_hour' => $appTimerData['count'],     // отдаем уже поделенное: например, 8 (дней) или 18 (часов)
                'is_overdue' => $appTimerData['is_overdue'],
                'is_day' => $appTimerData['is_day'],
                'display_text' => $appTimerData['display_text'],
                'percent' => $this->calcPercent($rawCountHours, $rawMaxHours),
            ],
            'finance_timer' => [
                'has_timer' => $rawMaxHoursF > 0,
                'max_hour_f' => $finTimerData['max'],       // отдаем уже поделенное: например, 44 (дня)
                'count_hour_f' => $finTimerData['count'],     // отдаем уже поделенное: например, 5 (дней)
                'is_overdue' => $finTimerData['is_overdue'],
                'is_day' => $finTimerData['is_day'],
                'display_text' => $finTimerData['display_text'],
                'percent' => $this->calcPercent($rawCountHoursF, $rawMaxHoursF),
            ],
        ];
    }

    /**
     * Делим на 24, если countHours >= 24, иначе отдаем часы
     */
    /**
     * Делим на 24 (берем строго целое от деления), если countHours >= 24, иначе отдаем часы
     */
    private function transformHoursToOutput(int $countHours, int $maxHours, int $statusId, bool $isFinance = false): array
    {
        if ($statusId <= ($isFinance ? 1 : 0) || $maxHours <= 0) {
            return [
                'max' => 0,
                'count' => 0,
                'is_day' => 0,
                'is_overdue' => false,
                'display_text' => '—',
            ];
        }

        // Архив (25) и Корзина (27)
        if (in_array($statusId, [25, 27], true)) {
            return [
                'max' => 0,
                'count' => 0,
                'is_day' => 0,
                'is_overdue' => false,
                'display_text' => $statusId === 25 ? 'Архив' : 'Корзина',
            ];
        }

        // Просрочено (отрицательные часы)
        if ($countHours < 0) {
            return [
                'max' => $maxHours >= 24 ? intdiv($maxHours, 24) : $maxHours,
                'count' => $countHours,
                'is_day' => 0,
                'is_overdue' => true,
                'display_text' => 'просрочено',
            ];
        }

        // Если осталось 24 часа и более -> берем ЦЕЛОЕ от деления на 24 (intdiv)
        if ($countHours >= 24) {
            $daysLeft = intdiv($countHours, 24);
            $daysMax = intdiv($maxHours, 24);

            return [
                'max' => $daysMax,
                'count' => $daysLeft,
                'is_day' => 1,
                'is_overdue' => false,
                'display_text' => "{$daysLeft} из {$daysMax} дн.",
            ];
        }

        // Если осталось менее 24 часов -> отдаем часы как есть (is_day = 0)
        return [
            'max' => $maxHours,
            'count' => $countHours,
            'is_day' => 0,
            'is_overdue' => false,
            'display_text' => "{$countHours} из {$maxHours} ч.",
        ];
    }

    private function calcPercent(int $count, int $max): float
    {
        if ($count < 0) {
            return 100.0;
        }

        if ($max <= 0) {
            return 0.0;
        }

        return (float) min(100, max(0, round(($count / $max) * 100, 1)));
    }
}