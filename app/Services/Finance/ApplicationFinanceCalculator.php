<?php

namespace App\Services\Finance;

use InvalidArgumentException;

final class ApplicationFinanceCalculator
{
    /**
     * Входные данные:
     *
     * approved:
     *   amount — согласованная сумма до применения режима НДС
     *
     * vat:
     *   mode — added | included
     *   rate — ставка в сотых долях процента
     *
     * planned:
     *   parts_pzn
     *   repair_pzn
     *   paint_pzn
     *   materials
     *   selected_parts
     *   works
     *
     * actual:
     *   parts_after_order
     *   ordered_parts
     *   works
     *   extra_expenses
     *
     * cash:
     *   income
     *   income_date — YYYY-MM-DD | null
     *   sto_payment_date — YYYY-MM-DD | null
     *
     * income_tax_rate — ставка в сотых долях процента
     *
     * Все денежные значения — целые копейки.
     * Названия ключей являются внутренним контрактом сервиса,
     * а не названиями колонок существующей БД.
     */
    public function calculate(array $input): array
    {
        $approved = $this->money($input['approved']['amount']);

        $vatMode = $input['vat']['mode'];
        $vatRate = $this->rate($input['vat']['rate']);
        $taxRate = $this->rate($input['income_tax_rate']);

        if (!in_array($vatMode, ['added', 'included'], true)) {
            throw new InvalidArgumentException(
                'Неизвестный режим НДС.'
            );
        }

        $planned = $input['planned'];
        $actual = $input['actual'];
        $cash = $input['cash'];

        $materials = $this->money($planned['materials']);
        $plannedParts = $this->money($planned['selected_parts']);
        $plannedWorks = $this->money($planned['works']);

        $plannedExpenses = $plannedParts
            + $plannedWorks
            + $materials;

        $plannedTotal = $this->money($planned['parts_pzn'])
            + $this->money($planned['repair_pzn'])
            + $this->money($planned['paint_pzn'])
            + $materials;

        $approvedVat = $vatMode === 'added'
            ? $this->percentage($approved, $vatRate)
            : $this->includedVat($approved, $vatRate);

        $approvedGross = $vatMode === 'added'
            ? $approved + $approvedVat
            : $approved;

        $approvedNet = $approvedGross - $approvedVat;

        // По ТЗ: налог рассчитывается от полной согласованной суммы дохода.
        $approvedIncomeTax = $this->percentage(
            $approvedGross,
            $taxRate
        );

        $approvedTaxes = $approvedVat + $approvedIncomeTax;

        $approvedDifference = $approvedGross
            - $plannedExpenses
            - $approvedTaxes;

        /*
         * Плановая сумма трактуется по тому же режиму НДС,
         * что и согласованная.
         *
         * total — исходная сумма ПЗН;
         * gross — плановая сумма с НДС.
         */
        $plannedVat = $vatMode === 'added'
            ? $this->percentage($plannedTotal, $vatRate)
            : $this->includedVat($plannedTotal, $vatRate);

        $plannedGross = $vatMode === 'added'
            ? $plannedTotal + $plannedVat
            : $plannedTotal;

        $plannedIncomeTax = $this->percentage(
            $plannedGross,
            $taxRate
        );

        $plannedTaxes = $plannedVat + $plannedIncomeTax;

        $plannedDifference = $plannedGross
            - $plannedExpenses
            - $plannedTaxes;

        $actualParts = $this->money($actual['parts_after_order']);
        $orderedParts = $this->money($actual['ordered_parts']);
        $actualWorks = $this->money($actual['works']);
        $extraExpenses = $this->money($actual['extra_expenses']);

        $actualWorksAndExtras = $actualWorks + $extraExpenses;
        $actualExpenses = $actualParts + $actualWorksAndExtras;

        $income = $this->money($cash['income']);

        $stoPaymentDate = $cash['sto_payment_date'] ?? null;
        $worksPaid = $stoPaymentDate !== null
            && $stoPaymentDate !== '';

        $cashExpenses = $orderedParts
            + $extraExpenses
            + ($worksPaid ? $actualWorks : 0);

        /*
         * Доход — реальное банковское поступление.
         * НДС выделяется из полученной суммы, а не добавляется к ней.
         */
        $cashVat = $this->includedVat($income, $vatRate);
        $incomeTax = $this->percentage($income, $taxRate);

        $actualProfit = $income
            - $cashExpenses
            - $cashVat
            - $incomeTax;

        return [
            'money_unit' => 'kopeck',

            'approved' => [
                'entered' => $approved,
                'net' => $approvedNet,
                'vat' => $approvedVat,
                'gross' => $approvedGross,

                'vat_mode' => $vatMode,
                'vat_rate' => $vatRate,
                'without_vat' => $vatRate === 0,

                'income_tax_rate' => $taxRate,
                'income_tax' => $approvedIncomeTax,
                'taxes_total' => $approvedTaxes,
            ],

            'approved_difference' => $approvedDifference,

            'planned' => [
                // Исходную сумму ПЗН сохраняем в прежнем поле.
                'total' => $plannedTotal,

                'gross' => $plannedGross,
                'net' => $plannedGross - $plannedVat,

                'vat' => $plannedVat,
                'vat_rate' => $vatRate,
                'vat_mode' => $vatMode,

                'income_tax' => $plannedIncomeTax,
                'income_tax_rate' => $taxRate,
                'taxes_total' => $plannedTaxes,

                'difference' => $plannedDifference,

                'parts_pzn' => $this->money($planned['parts_pzn']),
                'repair_pzn' => $this->money($planned['repair_pzn']),
                'paint_pzn' => $this->money($planned['paint_pzn']),
                'materials' => $materials,
            ],

            'planned_expenses' => [
                'parts' => $plannedParts,
                'works' => $plannedWorks,
                'materials' => $materials,
                'total' => $plannedExpenses,
            ],

            'actual_expenses' => [
                'parts' => $actualParts,
                'works' => $actualWorks,
                'extra_expenses' => $extraExpenses,
                'works_and_extras' => $actualWorksAndExtras,
                'total' => $actualExpenses,
            ],

            'cash' => [
                'income' => $income,
                'income_date' => $cash['income_date'] ?? null,

                'ordered_parts' => $orderedParts,
                'extra_expenses' => $extraExpenses,
                'paid_works' => $worksPaid ? $actualWorks : 0,

                'expense' => $cashExpenses,
                'sto_payment_date' => $stoPaymentDate,

                'vat' => $cashVat,
                'vat_rate' => $vatRate,

                'income_tax' => $incomeTax,
                'income_tax_rate' => $taxRate,

                'profit' => $actualProfit,
            ],
        ];
    }

    /**
     * Определение ставки сотрудника.
     *
     * 0 — установленная ставка.
     * null — индивидуальная ставка не установлена.
     */
    public function salaryRate(
        string $role,
        ?int $customRate,
        ?int $defaultRate,
        bool $hasMentor
    ): int {
        if (!in_array($role, ['mrk', 'mentor'], true)) {
            throw new InvalidArgumentException(
                'Расчёт ЗП доступен для МРК или наставника.'
            );
        }

        if ($customRate !== null) {
            return $this->rate($customRate);
        }

        if ($role === 'mrk' && !$hasMentor) {
            return 1400;
        }

        if ($defaultRate === null) {
            throw new InvalidArgumentException(
                'У сотрудника не установлена ставка по умолчанию.'
            );
        }

        return $this->rate($defaultRate);
    }

    /**
     * Расчётное значение процента от прибыли.
     * Не является проведённым начислением зарплаты.
     */
    public function salaryAmount(int $profit, int $rate): int
    {
        return $this->percentage(
            max(0, $profit),
            $this->rate($rate)
        );
    }

    private function money(mixed $value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException(
                'Исходная сумма должна быть целым неотрицательным '
                . 'числом копеек.'
            );
        }

        return $value;
    }

    private function rate(mixed $value): int
    {
        if (!is_int($value) || $value < 0 || $value > 10000) {
            throw new InvalidArgumentException(
                'Ставка должна находиться в диапазоне от 0 до 100%.'
            );
        }

        return $value;
    }

    private function percentage(int $amount, int $rate): int
    {
        $sign = $amount < 0 ? -1 : 1;

        return $sign * intdiv(
            abs($amount) * $rate + 5000,
            10000
        );
    }

    private function includedVat(int $gross, int $rate): int
    {
        if ($rate === 0 || $gross === 0) {
            return 0;
        }

        $denominator = 10000 + $rate;

        return intdiv(
            $gross * $rate + intdiv($denominator, 2),
            $denominator
        );
    }
}