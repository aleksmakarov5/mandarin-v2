<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ApplicationWorkExpenseReader
{
    /**
     * Возвращает суммы в рублях как десятичные строки.
     *
     * Здесь нет преобразования в float.
     * В расчётное ядро суммы передаются после перевода в копейки.
     */
    public function read(int $applicationId): array
    {
        if ($applicationId <= 0) {
            throw new InvalidArgumentException(
                'Не указан корректный ID дела.'
            );
        }

        $plannedWorks = DB::table('plan_works')
            ->where('application_id', $applicationId)
            ->where('approval_status', 'approved')
            ->sum('price');

        $actualWorks = DB::table('fact_works')
            ->where('application_id', $applicationId)
            ->sum('price');

        $extraExpenses = DB::table('extra_expenses')
            ->where('application_id', $applicationId)
            ->sum('sum');

        return [
            'planned_works' => (string) $plannedWorks,
            'actual_works' => (string) $actualWorks,
            'extra_expenses' => (string) $extraExpenses,
        ];
    }
}