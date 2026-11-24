<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Estimate;
use App\Models\OurCompany;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApplicationFinanceService
{
    public function calculate(int|Application $appOrId, ?User $currentUser = null): array
    {
        $app = $appOrId instanceof Application
            ? $appOrId
            : Application::with(['insuranceCompany', 'ourCompany', 'mrk', 'mentor'])->findOrFail($appOrId);

        $currentUser = $currentUser ?? auth()->user();

        $estimate = Estimate::where('id_application', $app->id)->first();
        $ourCompany = OurCompany::find($app->our_company);
        $insurance = $app->insuranceCompany;

        // Ставки юрлица
        $ndsRate = (float) ($ourCompany->nds_rate ?? 20.0);
        $incomeTaxRate = (float) ($ourCompany->income_tax_rate ?? 6.0);
        $ndsMode = (int) ($app->nds_mode ?? $app->nds ?? 0); // 0 - включая НДС, 1 - сумма + НДС
        $splitIc = (bool) ($insurance->split_ic_total ?? false);

        // --- 1. СУММА СК СОГЛАСОВАННАЯ ---
        $isKasko = (bool) ($app->ub_type == 1);
        $icRepair = (float) ($estimate->ic_repair_sum ?? 0);
        $icDefect = (float) ($estimate->ic_defect_sum ?? 0);

        if ($splitIc && ($icRepair > 0 || $icDefect > 0)) {
            $sumApprovedBase = $icRepair + $icDefect;
        } else {
            if ($isKasko) {
                $sumApprovedBase = (float) ($estimate->summ_ic_half ?? $estimate->price_repair_half ?? 0);
            } else {
                $sumApprovedBase = (float) ($estimate->summ_ic_full ?? $estimate->price_repair_full ?? 0);
            }
            if (isset($estimate->add_pay)) {
                $sumApprovedBase += (float) $estimate->add_pay;
            }
        }

        // Расчёт НДС
        $ndsApprovedAmount = 0;
        $sumApprovedWithNds = $sumApprovedBase;
        if ($ndsRate > 0) {
            if ($ndsMode === 1) { // сумма + НДС
                $ndsApprovedAmount = round($sumApprovedBase * ($ndsRate / 100), 2);
                $sumApprovedWithNds = $sumApprovedBase + $ndsApprovedAmount;
            } else { // сумма включая НДС
                $ndsApprovedAmount = round($sumApprovedBase - ($sumApprovedBase / (1 + $ndsRate / 100)), 2);
                $sumApprovedWithNds = $sumApprovedBase;
            }
        }

        // --- Загрузка запчастей и работ ---
        $spareParts = DB::table('spare_parts')->where('id_application', $app->id)->get();
        $works = DB::table('works')->where('id_application', $app->id)->get();

        // --- 5. РАСХОДЫ ПЛАНОВЫЕ ---
        $planPartsCost = 0;
        $factPartsCost = 0; // для блока 6

        foreach ($spareParts as $sp) {
            $selectedPrice = 0;
            switch ((int) $sp->selected_mrk) {
                case 1:
                    $selectedPrice = (float) $sp->price_original;
                    break;
                case 2:
                    $selectedPrice = (float) $sp->price_analogue;
                    break;
                case 3:
                    $selectedPrice = (float) $sp->price_used;
                    break;
                case 4:
                    $selectedPrice = (float) ($sp->price_service ?? $sp->price_sto ?? 0);
                    break;
            }
            $planPartsCost += $selectedPrice;

            // Блок 6: запчасти со статусом выше, чем «заказана» (например статус заказа > 2)
            $spStatus = (int) ($sp->status_order ?? $sp->id_status ?? 0);
            if ($selectedPrice > 0 && $spStatus > 2) {
                $factPartsCost += $selectedPrice;
            }
        }

        // Плановые и фактические работы
        $planWorksCost = 0;
        $factWorksCost = 0;
        $planPznWorks = 0;
        $planPznPaint = 0;

        foreach ($works as $w) {
            $servicePrice = (float) ($w->price_service ?? $w->summ ?? 0);
            $planWorksCost += $servicePrice;

            // Фактические и дополнительные работы
            $factServicePrice = (float) ($w->price_fact ?? $w->price_service ?? 0);
            $extraPrice = (float) ($w->price_extra ?? 0);
            $factWorksCost += ($factServicePrice + $extraPrice);

            // ПЗН
            $hours = (float) ($w->count_hours_pzn ?: $w->count_hours ?: 1);
            $rate = (float) ($w->price ?: 0);
            $pznSum = $hours * $rate;

            if ((int) ($w->type_work ?? 0) === 2 || str_contains(mb_strtolower($w->name ?? ''), 'окрас')) {
                $planPznPaint += $pznSum;
            } else {
                $planPznWorks += $pznSum;
            }
        }

        // Расходные материалы (из сметы или дефолт)
        $planMaterialsCost = (float) ($estimate->paint_materials_plan ?? $estimate->price_materials ?? 0);

        // Итого плановые расходы
        $totalPlanExpenses = $planPartsCost + $planWorksCost + $planMaterialsCost;

        // --- 2. РАЗНИЦА ОДОБРЕННАЯ ---
        $diffApproved = $sumApprovedBase - $totalPlanExpenses;

        // --- 3. СУММА ПЛАНОВАЯ (ПЗН запчасти + ПЗН рем. + ПЗН окрас + мат.) ---
        $planPznParts = 0;
        foreach ($spareParts as $sp) {
            $pznPartPrice = (float) ($sp->price_pzn ?: ($isKasko ? $sp->price_ic_half : $sp->price_ic_full) ?: 0);
            $planPznParts += $pznPartPrice;
        }
        $sumPlan = $planPznParts + $planPznWorks + $planPznPaint + $planMaterialsCost;

        // --- 4. РАЗНИЦА ПЛАНОВАЯ ---
        $diffPlan = $sumPlan - $totalPlanExpenses;

        // --- 6. РАСХОДЫ ФАКТИЧЕСКИЕ ---
        $totalFactExpenses = $factPartsCost + $factWorksCost;

        // --- 7. ДДС (Движение Денежных Средств) ---
        $ddsIncomeSum = (float) ($estimate->dds_income_sum ?? 0);
        $ddsIncomeDate = $estimate->dds_income_date ?? null;
        $ddsStoPayDate = $estimate->dds_sto_pay_date ?? $app->sto_pay_date ?? null;
        $ddsExtraExpenses = (float) ($estimate->dds_extra_expenses ?? 0);

        // Фактический расход: заказанные запчасти + доп. расходы + работы (если дата расчета с СТО проставлена)
        $ddsExpenseSum = $factPartsCost + $ddsExtraExpenses;
        if (!empty($ddsStoPayDate)) {
            $ddsExpenseSum += $factWorksCost;
        }

        // НДС от дохода
        $ddsNdsSum = 0;
        if ($ndsRate > 0 && $ddsIncomeSum > 0) {
            if ($ndsMode === 1) {
                $ddsNdsSum = round($ddsIncomeSum * ($ndsRate / 100), 2);
            } else {
                $ddsNdsSum = round($ddsIncomeSum - ($ddsIncomeSum / (1 + $ndsRate / 100)), 2);
            }
        }

        // Налог на прибыль от фактического дохода
        $ddsTaxIncomeSum = round($ddsIncomeSum * ($incomeTaxRate / 100), 2);

        // Фактическая прибыль = Доходы - Расходы - Налоги (НДС при режиме сумма+НДС не уменьшает, при включая НДС вычитается)
        $taxDeduction = ($ndsMode === 0 ? $ddsNdsSum : 0) + $ddsTaxIncomeSum;
        $factProfit = $ddsIncomeSum - $ddsExpenseSum - $taxDeduction;

        // --- 8. ЗП (для МРК или Наставника) ---
        $zpData = null;
        $isMrk = $currentUser && ((int) $app->id_MRK === (int) $currentUser->id);
        $isMentor = $currentUser && ((int) ($app->id_mentor ?? $app->id_spm) === (int) $currentUser->id);
        $isAdminOrOwner = $currentUser && ($currentUser->isSystemAdmin() || $currentUser->hasRole('owner') || $currentUser->hasRole('company'));

        if ($isMrk || $isMentor || $isAdminOrOwner) {
            $userRoleType = $isMentor ? 'mentor' : 'mrk';
            $hasMentor = !empty($app->id_mentor) || !empty($app->id_spm);

            // Процент для МРК
            $mrkPercent = $app->mrk_proc_custom !== null
                ? (float) $app->mrk_proc_custom
                : ($hasMentor ? (float) ($currentUser->def_mrk_proc ?? 10.0) : 14.0);

            // Процент для Наставника
            $mentorPercent = $app->mentor_proc_custom !== null
                ? (float) $app->mentor_proc_custom
                : (float) ($currentUser->def_mrk_proc ?? 4.0);

            $targetPercent = $isMentor ? $mentorPercent : $mrkPercent;

            // База для ЗП: берётся от фактической прибыли (если ДДС доход получен), либо от одобренной выгоды
            $baseForZp = $factProfit > 0 ? $factProfit : max(0, $diffApproved);
            $zpAmount = round(($baseForZp * $targetPercent) / 100, 2);

            $zpData = [
                'visible' => true,
                'role' => $userRoleType,
                'percent' => $targetPercent,
                'amount' => $zpAmount,
                'base' => $baseForZp,
                'mrk_percent' => $mrkPercent,
                'mentor_percent' => $mentorPercent,
                'can_edit_percent' => $isAdminOrOwner,
            ];
        }

        return [
            'app_id' => $app->id,
            'is_kasko' => $isKasko,
            'our_company' => [
                'id' => $ourCompany->id ?? null,
                'name' => $ourCompany->name ?? '',
                'nds_rate' => $ndsRate,
                'income_tax_rate' => $incomeTaxRate,
            ],
            'insurance' => [
                'id' => $insurance->id ?? null,
                'name' => $insurance->name ?? '',
                'split_ic_total' => $splitIc,
            ],
            // 1. Сумма СК
            'approved' => [
                'base' => $sumApprovedBase,
                'repair_sum' => $icRepair,
                'defect_sum' => $icDefect,
                'is_split' => $splitIc,
                'nds_mode' => $ndsMode, // 0 - включая, 1 - плюс НДС
                'nds_rate' => $ndsRate,
                'nds_amount' => $ndsApprovedAmount,
                'total_with_nds' => $sumApprovedWithNds,
                'nds_badge_text' => $ndsRate == 0 ? 'Без НДС' : ($ndsMode === 1 ? "+НДС {$ndsRate}%" : "вкл. НДС {$ndsRate}%"),
            ],
            // 2. Разница одобренная
            'diff_approved' => [
                'value' => $diffApproved,
                'percent' => $sumApprovedBase > 0 ? round(($diffApproved / $sumApprovedBase) * 100, 1) : 0,
            ],
            // 3. Сумма плановая
            'plan_sum' => [
                'total' => $sumPlan,
                'parts' => $planPznParts,
                'repair_works' => $planPznWorks,
                'paint_works' => $planPznPaint,
                'materials' => $planMaterialsCost,
            ],
            // 4. Разница плановая
            'diff_plan' => [
                'value' => $diffPlan,
                'percent' => $sumPlan > 0 ? round(($diffPlan / $sumPlan) * 100, 1) : 0,
            ],
            // 5. Расходы плановые
            'expenses_plan' => [
                'total' => $totalPlanExpenses,
                'parts' => $planPartsCost,
                'works' => $planWorksCost,
                'materials' => $planMaterialsCost,
            ],
            // 6. Расходы фактические
            'expenses_fact' => [
                'total' => $totalFactExpenses,
                'parts' => $factPartsCost,
                'works' => $factWorksCost,
            ],
            // 7. ДДС
            'dds' => [
                'income_sum' => $ddsIncomeSum,
                'income_date' => $ddsIncomeDate,
                'expense_sum' => $ddsExpenseSum,
                'sto_pay_date' => $ddsStoPayDate,
                'nds_rate' => $ndsRate,
                'nds_sum' => $ddsNdsSum,
                'income_tax_rate' => $incomeTaxRate,
                'income_tax_sum' => $ddsTaxIncomeSum,
                'fact_profit' => $factProfit,
            ],
            // 8. ЗП
            'zp' => $zpData,
        ];
    }
}