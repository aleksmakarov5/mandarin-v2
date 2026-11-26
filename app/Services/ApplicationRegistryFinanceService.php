<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ApplicationRegistryFinanceService
{
    public function __construct(
        protected ApplicationFinanceService $financeService
    ) {
    }

    public function decorate(
        Collection $applications,
        User $user
    ): Collection {
        foreach ($applications as $application) {
            try {
                $result = $this->financeService->calculate(
                    $application,
                    $user
                );

                $summary = is_array($result['data'] ?? null)
                    ? $result['data']
                    : $result;

                $application->setAttribute(
                    'registry_finance',
                    $this->makeRegistryFinance($summary)
                );
            } catch (\Throwable $e) {
                Log::error(
                    'Ошибка расчёта финансов реестра.',
                    [
                        'application_id' => $application->id,
                        'error' => $e->getMessage(),
                    ]
                );

                $application->setAttribute(
                    'registry_finance',
                    $this->emptyFinance()
                );
            }
        }

        return $applications;
    }

    protected function makeRegistryFinance(array $summary): array
    {
        $approved = $summary['approved'] ?? [];
        $plannedExpenses = $summary['planned_expenses'] ?? [];
        $actualExpenses = $summary['actual_expenses'] ?? [];
        $cash = $summary['cash'] ?? [];

        $isSplit = (bool) ($cash['is_split']
            ?? $approved['is_split']
            ?? false);

        $repairIncome = $this->rubles(
            $cash['repair_income'] ?? 0
        );

        $defectIncome = $this->rubles(
            $cash['defect_income'] ?? 0
        );

        $singleIncome = $this->rubles(
            $cash['income'] ?? 0
        );

        /*
         * При раздельной оплате фактический режим включается
         * только после получения обеих частей.
         */
        $incomeReceived = $isSplit
            ? $repairIncome > 0 && $defectIncome > 0
            : $singleIncome > 0;

        $approvedSum = $this->rubles(
            $approved['gross'] ?? $approved['total'] ?? 0
        );

        $actualIncome = $isSplit
            ? $repairIncome + $defectIncome
            : $singleIncome;

        $sumSk = $incomeReceived
            ? $actualIncome
            : $approvedSum;

        $plannedParts = $this->rubles(
            $plannedExpenses['parts'] ?? 0
        );

        $factParts = $this->rubles(
            $actualExpenses['parts'] ?? 0
        );

        $plannedWorks = $this->rubles(
            $plannedExpenses['works'] ?? 0
        );

        $factWorks = $this->rubles(
            $actualExpenses['works'] ?? 0
        );

        /*
         * В карточке actual_expenses.works уже содержит
         * фактические работы, а extra_expenses хранится отдельно.
         */
        $extraCosts = $this->rubles(
            $actualExpenses['extra_expenses'] ?? 0
        );

        $sumZap = $incomeReceived
            ? $factParts
            : $plannedParts;

        $repair = $incomeReceived
            ? $factWorks
            : $plannedWorks;

        /*
         * Остаток берём из того же расчёта, что и карточка.
         *
         * При фактическом доходе:
         * cash.profit уже учитывает расход ДДС,
         * НДС и налог.
         *
         * До поступления:
         * approved_difference учитывает согласованную сумму,
         * плановые расходы и налоги.
         */
        $remainsKopecks = $incomeReceived
            ? ($cash['profit'] ?? null)
            : ($summary['approved_difference'] ?? null);

        $remains = $remainsKopecks === null
            ? null
            : $this->rubles($remainsKopecks);

        $remainsPercent = $sumSk > 0 && $remains !== null
            ? round(($remains / $sumSk) * 100, 2)
            : null;

        /*
         * Дата оплаты СК.
         *
         * При едином платеже выводится его дата.
         * При двух платежах дата выводится только если
         * указаны обе даты; берётся более поздняя.
         */
        $paidSkDate = null;

        if (!$isSplit) {
            $paidSkDate = $cash['income_date'] ?? null;
        } else {
            $repairDate = $cash['repair_income_date'] ?? null;
            $defectDate = $cash['defect_income_date'] ?? null;

            if ($repairDate && $defectDate) {
                $paidSkDate = max(
                    substr((string) $repairDate, 0, 10),
                    substr((string) $defectDate, 0, 10)
                );
            }
        }

        return [
            'sum_sk' => $sumSk,

            'sum_zap' => $sumZap,

            'repair' => $repair,

            'extra_costs' => $extraCosts,

            'paid_sk_date' => $paidSkDate,

            'paid_sto_date' => $cash['sto_payment_date'] ?? null,

            'remains' => $remains,

            'remains_percent' => $remainsPercent,

            'income_received' => $incomeReceived,

            'basis' => $incomeReceived
                ? 'fact'
                : 'plan',

            'vat' => $this->rubles(
                $incomeReceived
                ? ($cash['vat'] ?? 0)
                : ($approved['vat'] ?? 0)
            ),

            'income_tax' => $this->rubles(
                $incomeReceived
                ? ($cash['income_tax'] ?? 0)
                : ($approved['income_tax'] ?? 0)
            ),

            'planned_parts' => $plannedParts,
            'fact_parts' => $factParts,
            'planned_works' => $plannedWorks,
            'fact_works' => $factWorks,
        ];
    }

    protected function rubles(mixed $kopecks): ?float
    {
        if ($kopecks === null) {
            return null;
        }

        return round((float) $kopecks / 100, 2);
    }

    protected function emptyFinance(): array
    {
        return [
            'sum_sk' => 0,
            'sum_zap' => 0,
            'repair' => 0,
            'extra_costs' => 0,
            'paid_sk_date' => null,
            'paid_sto_date' => null,
            'remains' => null,
            'remains_percent' => null,
            'income_received' => false,
            'basis' => 'plan',
            'vat' => 0,
            'income_tax' => 0,
            'planned_parts' => 0,
            'fact_parts' => 0,
            'planned_works' => 0,
            'fact_works' => 0,
        ];
    }
}