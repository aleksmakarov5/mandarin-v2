<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use App\Services\Finance\ApplicationFinanceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use UnexpectedValueException;

class ApplicationFinanceService
{
    public function __construct(
        protected ApplicationFinanceCalculator $calculator
    ) {
    }

    /**
     * Вызывается после проверки доступа в контроллере.
     *
     * Все денежные значения результата — целые копейки.
     */
    public function calculate(
        Application $app,
        User $currentUser
    ): array {
        $warnings = [];

        $app->loadMissing([
            'insuranceCompany',
            'ourCompany.legalEntity',
        ]);

        $estimate = DB::table('estimates')
            ->where('application_id', $app->id)
            ->first();

        if (!$estimate) {
            $warnings[] = 'Смета дела ещё не создана.';
        }

        $legalEntity = $app->ourCompany?->legalEntity;

        $vatRate = $legalEntity?->nds_rate;
        $incomeTaxRate = $legalEntity?->income_tax_rate;

        if ($legalEntity === null) {
            $warnings[] = 'У филиала не назначено юридическое лицо.';
        }

        if ($vatRate === null) {
            $warnings[] = 'Не настроена ставка НДС юридического лица.';
        }

        if ($incomeTaxRate === null) {
            $warnings[] = 'Не настроена ставка налога с дохода.';
        }

        $isCasco = (int) $app->ub_type === 1;
        $isSplit = (bool) (
            $app->insuranceCompany?->split_ic_total ?? false
        );

        $repair = $this->toHundredths(
            $estimate->ic_repair_sum ?? 0
        );

        $defect = $this->toHundredths(
            $estimate->ic_defect_sum ?? 0
        );

        /*
         * Сохраняем действовавшее правило:
         * в раздельном режиме сумма = ремонт + дефектовка;
         * в обычном режиме = итог калькуляции + доплата.
         */
        $approved = $isSplit
            ? $repair + $defect
            : $this->toHundredths(
                $isCasco
                ? ($estimate->price_repair_half ?? 0)
                : ($estimate->price_repair_full ?? 0)
            ) + $this->toHundredths(
                    $estimate->add_pay ?? 0
                );

        $parts = DB::table('spare_parts')
            ->where('application_id', $app->id)
            ->get();

        $selectedParts = 0;
        $partsPzn = 0;
        $orderedParts = 0;
        $partsAfterOrder = 0;

        /*
         * Явные стадии, а не сравнение числовых ID.
         * Сейчас в БД встречаются только «В поиске»
         * и «Заказана».
         */
        $afterOrderStatuses = [
            'ожидается доставка',
            'на складе',
            'выдана на сто',
            'установлена',
        ];

        $knownStatuses = [
            'в поиске',
            'заказана',
            'ожидается доставка',
            'на складе',
            'выдана на сто',
            'установлена',
            'отказ',
        ];

        foreach ($parts as $part) {
            $selected = $this->selectedPartPrice($part);
            $selectedParts += $selected;

            $partsPzn += $this->toHundredths(
                $part->price_pzn ?? 0
            );

            $status = mb_strtolower(
                trim((string) ($part->status ?? ''))
            );

            $isAfterOrder = in_array(
                $status,
                $afterOrderStatuses,
                true
            );

            if ($status === 'заказана' || $isAfterOrder) {
                $orderedParts += $selected;
            }

            if ($status === 'заказана' || $isAfterOrder) {
                $partsAfterOrder += $selected;
            }

            if (!in_array($status, $knownStatuses, true)) {
                $warnings[] =
                    'Обнаружен нераспознанный статус запчасти: '
                    . ($status !== '' ? $status : 'пустой')
                    . '. Его стадия не учтена в факте и ДДС.';
            }
        }

        $repairPzn = 0;
        $paintPzn = 0;

        $works = DB::table('works')
            ->where('application_id', $app->id)
            ->get();

        foreach ($works as $work) {
            $pznHours = $work->hours_pzn
                ?? $work->pzn_hours
                ?? null;

            /*
             * По действовавшему правилу:
             * нет часов ПЗН или они равны 0 — сумма СК;
             * иначе часы ПЗН × ставка.
             */
            $sum = $pznHours !== null
                && (float) $pznHours > 0
                ? $this->multiplyHours(
                    $work->price ?? $work->price_per_hour ?? 0,
                    $pznHours
                )
                : $this->toHundredths(
                    $work->sum_sk ?? $work->sum_ic ?? 0
                );

            $isPaint = property_exists($work, 'is_paint')
                && $work->is_paint !== null
                ? (bool) $work->is_paint
                : ($work->work_category ?? '') === 'paint';

            if ($isPaint) {
                $paintPzn += $sum;
            } else {
                $repairPzn += $sum;
            }
        }

        /*
         * Учитываем принятые позиции плана.
         * Нерассмотренные и отклонённые не включаются.
         */
        $planWorks = $this->sumRows(
            'plan_works',
            $app->id,
            ['price'],
            true
        );

        $factWorks = $this->sumRows(
            'fact_works',
            $app->id,
            ['price', 'price_service', 'price_sto', 'sum', 'cost']
        );

        $extraExpenses = $this->sumRows(
            'extra_expenses',
            $app->id,
            ['sum']
        );

        /*
         * saveMaterials в текущем модуле мог сохранять
         * значение в applications.
         * Если атрибут существует и не NULL — он приоритетен,
         * в том числе при установленном нуле.
         */
        $materials = $this->toHundredths(
            $app->getAttributes()['paint_materials_plan']
            ?? $estimate->paint_materials_plan
            ?? 0
        );

        $vatMode = (int) ($app->nds_mode ?? 0) === 1
            ? 'added'
            : 'included';


        $cashSingle = $this->toHundredths(
            $estimate->dds_income_sum ?? 0
        );

        $cashRepair = $this->toHundredths(
            $estimate->dds_repair_income_sum ?? 0
        );

        $cashDefect = $this->toHundredths(
            $estimate->dds_defect_income_sum ?? 0
        );

        $cashIncome = $isSplit
            ? $cashRepair + $cashDefect
            : $cashSingle;

        $cashIncomeDate = $isSplit
            ? null
            : ($estimate->dds_income_date ?? null);

        $stoPaymentDate = $estimate->dds_sto_pay_date ?? null;

        /*
         * Неактивные поля не удаляем и не учитываем в доходе.
         * Предупреждаем о необходимости сверки.
         */
        if ($isSplit && $cashSingle > 0) {
            $warnings[] =
                'Сохранено единое поступление из другого режима. '
                . 'В раздельном ДДС оно не учитывается: сверьте ремонт и дефектовку.';
        }

        if (!$isSplit && ($cashRepair > 0 || $cashDefect > 0)) {
            $warnings[] =
                'Сохранены раздельные поступления из другого режима. '
                . 'В едином ДДС они не учитываются: сверьте доход СК.';
        }

        /*
         * Калькулятор требует числовые ставки.
         * Если настройки отсутствуют, промежуточно
         * рассчитываем независимые показатели с нулём,
         * а ВСЕ зависимые результаты ниже заменяем на NULL.
         * Эти промежуточные нулевые налоги клиенту не выдаются.
         */
        $result = $this->calculator->calculate([
            'approved' => [
                'amount' => $approved,
            ],

            'vat' => [
                'mode' => $vatMode,
                'rate' => $this->toHundredths($vatRate ?? 0),
            ],

            'income_tax_rate' => $this->toHundredths(
                $incomeTaxRate ?? 0
            ),

            'planned' => [
                'parts_pzn' => $partsPzn,
                'repair_pzn' => $repairPzn,
                'paint_pzn' => $paintPzn,
                'materials' => $materials,
                'selected_parts' => $selectedParts,
                'works' => $planWorks,
            ],

            'actual' => [
                'parts_after_order' => $partsAfterOrder,
                'ordered_parts' => $orderedParts,
                'works' => $factWorks,
                'extra_expenses' => $extraExpenses,
            ],

            'cash' => [
                'income' => $cashIncome,
                'income_date' => $cashIncomeDate,
                'sto_payment_date' => $stoPaymentDate,
            ],
        ]);

        $result['cash']['is_split'] = $isSplit;

        $result['cash']['repair_income'] = $isSplit
            ? $cashRepair
            : null;

        $result['cash']['repair_income_date'] = $isSplit
            ? ($estimate->dds_repair_income_date ?? null)
            : null;

        $result['cash']['defect_income'] = $isSplit
            ? $cashDefect
            : null;

        $result['cash']['defect_income_date'] = $isSplit
            ? ($estimate->dds_defect_income_date ?? null)
            : null;

        $result['application_id'] = (int) $app->id;

        $result['legal_entity'] = $legalEntity
            ? [
                'id' => (int) $legalEntity->id,
                'name' => $legalEntity->name,
            ]
            : null;

        $result['approved']['is_split'] = $isSplit;
        $result['approved']['repair'] = $isSplit ? $repair : null;
        $result['approved']['defect'] = $isSplit ? $defect : null;

        if ($vatRate === null) {
            $result['planned']['vat_rate'] = null;
            $result['planned']['vat'] = null;
            $result['planned']['net'] = null;

            if ($vatMode === 'added') {
                $result['planned']['gross'] = null;
                $result['planned']['income_tax'] = null;
            }
        }

        if ($incomeTaxRate === null) {
            $result['planned']['income_tax_rate'] = null;
            $result['planned']['income_tax'] = null;
        }

        if ($vatRate === null || $incomeTaxRate === null) {
            $result['approved']['taxes_total'] = null;
            $result['approved_difference'] = null;

            $result['planned']['taxes_total'] = null;
            $result['planned']['difference'] = null;

            $result['cash']['profit'] = null;
        }

        if (
            $incomeTaxRate === null
            || ($vatRate === null && $vatMode === 'added')
        ) {
            $result['approved']['income_tax'] = null;
        }

        if ($incomeTaxRate === null) {
            $result['approved']['income_tax_rate'] = null;
            $result['cash']['income_tax_rate'] = null;
            $result['cash']['income_tax'] = null;
        }

        if ($vatRate === null || $incomeTaxRate === null) {
            $result['approved']['taxes_total'] = null;
            $result['approved_difference'] = null;
            $result['cash']['profit'] = null;
        }

        /*
         * Только собственная расчётная ЗП.
         * Право администрирования не превращается
         * в право выдавать чужую ЗП как собственную.
         */
        $salaryItems = [];
        $mentorId = (int) (
            $app->id_mentor ?? $app->mentor_id ?? 0
        );

        $ownRoles = [];

        if ((int) $app->id_MRK === (int) $currentUser->id) {
            $ownRoles['mrk'] = $app->mrk_proc_custom;
        }

        if ($mentorId > 0 && $mentorId === (int) $currentUser->id) {
            $ownRoles['mentor'] = $app->mentor_proc_custom;
        }

        foreach ($ownRoles as $role => $customRate) {
            $defaultRate = $currentUser->def_mrk_proc;

            if (
                $customRate === null
                && ($role === 'mentor' || $mentorId > 0)
                && $defaultRate === null
            ) {
                $warnings[] =
                    'Не настроен процент собственной ЗП: ' . $role;
                continue;
            }

            $rate = $this->calculator->salaryRate(
                $role,
                $customRate !== null
                ? $this->toHundredths($customRate)
                : null,
                $defaultRate !== null
                ? $this->toHundredths($defaultRate)
                : null,
                $mentorId > 0
            );

            $profit = $result['cash']['profit'];

            $salaryItems[] = [
                'role' => $role,
                'rate' => $rate,
                'amount' => $profit !== null
                    ? $this->calculator->salaryAmount($profit, $rate)
                    : null,
            ];
        }

        $result['salary_items'] = $salaryItems;

        /*
         * Совместимость с шапкой, где пока одна плашка ЗП.
         * Если пользователь одновременно МРК и наставник,
         * не выбираем одну роль произвольно.
         */
        $result['salary'] = count($salaryItems) === 1
            ? $salaryItems[0]
            : null;

        if (count($salaryItems) > 1) {
            $warnings[] =
                'Собственная ЗП рассчитана по двум назначениям '
                . 'и передана в salary_items.';
        }

        return [
            'data' => $result,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    protected function selectedPartPrice(object $part): int
    {
        $choice = $part->selected_mrk ?? null;

        if ($choice === null || $choice === '') {
            $choice = $part->selected_price ?? null;
        }

        $column = match ((string) $choice) {
            'original', '1' => 'price_original',
            'analogue', '2' => 'price_analogue',
            'used', '3' => 'price_used',
            'sto', '4' => 'price_sto',
            default => null,
        };

        return $column !== null
            ? $this->toHundredths($part->{$column} ?? 0)
            : 0;
    }

    protected function sumRows(
        string $table,
        int $applicationId,
        array $priceColumns,
        bool $approvedOnly = false
    ): int {
        $columns = Schema::getColumnListing($table);

        $priceColumn = null;

        foreach ($priceColumns as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $priceColumn = $candidate;
                break;
            }
        }

        if ($priceColumn === null) {
            throw new RuntimeException(
                "Не найдена колонка суммы в таблице {$table}."
            );
        }

        if (!in_array('application_id', $columns, true)) {
            throw new RuntimeException(
                "В таблице {$table} отсутствует application_id."
            );
        }

        $query = DB::table($table)
            ->where('application_id', $applicationId);

        if ($approvedOnly) {
            $statusColumn = in_array(
                'approval_status',
                $columns,
                true
            ) ? 'approval_status' : (
                in_array('status', $columns, true)
                ? 'status'
                : null
            );

            if ($statusColumn === null) {
                throw new RuntimeException(
                    'В plan_works отсутствует статус согласования.'
                );
            }

            $query->where($statusColumn, 'approved');
        }

        $sum = 0;

        foreach ($query->pluck($priceColumn) as $value) {
            $sum += $this->toHundredths($value ?? 0);
        }

        return $sum;
    }

    /**
     * Рубли → копейки либо проценты → сотые доли процента.
     *
     * Для DECIMAL-значений БД не используем
     * умножение двоичного float на 100.
     */
    protected function toHundredths(mixed $value): int
    {
        $value = trim((string) $value);

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new UnexpectedValueException(
                'Ожидалось неотрицательное число с точностью до сотых.'
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        return ((int) $whole * 100)
            + (int) str_pad($fraction, 2, '0');
    }

    protected function multiplyHours(
        mixed $rate,
        mixed $hours
    ): int {
        $rateInKopecks = $this->toHundredths($rate);
        $hoursInHundredths = $this->toHundredths($hours);

        return intdiv(
            $rateInKopecks * $hoursInHundredths + 50,
            100
        );
    }
}