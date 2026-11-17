<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApplicationTimerService
{
    /**
     * Расчет параметров таймера для основного или финансового статуса
     */
    public function calculate(object|array $application, ?object $status, string $type = 'app'): array
    {
        $app = (object) $application;

        if (!$status || empty($status->time)) {
            return [
                'has_timer' => false,
                'max_time' => 0,
                'current_time' => 0,
                'percent' => 100,
                'is_overdue' => false,
                'is_day' => true,
                'text' => null,
            ];
        }

        $maxTime = (int) $status->time;
        $isDay = (bool) ($status->is_day ?? true);

        // 1. Определение контрольной даты старта по логике статусов
        $startDate = $this->resolveStartDate($app, $status, $type);

        if (!$startDate) {
            return [
                'has_timer' => true,
                'max_time' => $maxTime,
                'current_time' => $maxTime,
                'percent' => 100,
                'is_overdue' => false,
                'is_day' => $isDay,
                'text' => "{$maxTime} " . ($isDay ? 'дн.' : 'ч.'),
            ];
        }

        // 2. Расчет дедлайна с учетом рабочих дней (Weekdays)
        $start = Carbon::parse($startDate);
        $now = Carbon::now();

        if ($isDay) {
            // Добавляем рабочие дни (пн-пт)
            $deadline = (clone $start)->addWeekdays($maxTime)->endOfDay();

            // Считаем остаток рабочих дней
            $diffDays = (int) $now->diffInWeekdays($deadline, false);

            // Если дата просрочена, diffInWeekdays возвращает отрицательное число
            $isOverdue = $now->greaterThan($deadline);
            $currentTime = $isOverdue ? -abs($diffDays) : max(0, $diffDays);

            $percent = $maxTime > 0
                ? max(0, min(100, round(($currentTime / $maxTime) * 100)))
                : 0;

            $text = $isOverdue
                ? 'Просрочено на ' . abs($currentTime) . ' дн.'
                : "Осталось {$currentTime} из {$maxTime} дн.";
        } else {
            // Расчет в часах
            $deadline = (clone $start)->addHours($maxTime);
            $diffHours = (int) $now->diffInHours($deadline, false);

            $isOverdue = $now->greaterThan($deadline);
            $currentTime = $isOverdue ? -abs($diffHours) : max(0, $diffHours);

            $percent = $maxTime > 0
                ? max(0, min(100, round(($currentTime / $maxTime) * 100)))
                : 0;

            $text = $isOverdue
                ? 'Просрочено на ' . abs($currentTime) . ' ч.'
                : "Осталось {$currentTime} из {$maxTime} ч.";
        }

        return [
            'has_timer' => true,
            'max_time' => $maxTime,
            'current_time' => $currentTime,
            'percent' => $isOverdue ? 100 : $percent,
            'is_overdue' => $isOverdue,
            'is_day' => $isDay,
            'text' => $text,
        ];
    }

    /**
     * Определение исходной даты в зависимости от логики статуса
     */
    private function resolveStartDate(object $app, object $status, string $type): ?string
    {
        $statusId = (int) ($status->id ?? 0);

        if ($type === 'app') {
            // Привязка контрольных дат по ID статусов
            return match ($statusId) {
                // Осмотр назначен -> от даты осмотра
                3 => $app->view_date ?? $app->created_at ?? null,
                // Направление получено -> от даты направления
                2 => $app->napr_date ?? $app->created_at ?? null,
                // Согласование / Ремонт -> от даты ответа СК или начала работ
                4, 5 => $app->ic_answer_date ?? $app->start_date ?? $app->created_at ?? null,
                // По умолчанию дата обновления статуса или создания
                default => $app->status_updated_at ?? $app->updated_at ?? $app->created_at ?? null,
            };
        }

        // Для финансовых статусов
        return match ($statusId) {
            2, 3 => $app->sp_date ?? $app->napr_date ?? $app->created_at ?? null,
            default => $app->finance_status_updated_at ?? $app->updated_at ?? $app->created_at ?? null,
        };
    }
}