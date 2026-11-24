<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Estimate;
use App\Services\ApplicationFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationFinanceController extends Controller
{
    public function __construct(protected ApplicationFinanceService $financeService)
    {
    }

    // Получить полную сводку для шапки
    public function summary(int $id, Request $request): JsonResponse
    {
        $summary = $this->financeService->calculate($id, $request->user());
        return response()->json($summary);
    }

    // Переключение режима НДС (включая / плюс)
    public function toggleNdsMode(int $id): JsonResponse
    {
        $app = Application::findOrFail($id);
        $app->nds_mode = ($app->nds_mode == 1) ? 0 : 1;
        $app->save();

        return response()->json([
            'success' => true,
            'nds_mode' => $app->nds_mode,
            'summary' => $this->financeService->calculate($app),
        ]);
    }

    // Быстрое редактирование суммы СК (на лету)
    public function updateApprovedSum(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'repair_sum' => 'nullable|numeric',
            'defect_sum' => 'nullable|numeric',
            'single_sum' => 'nullable|numeric',
        ]);

        $app = Application::findOrFail($id);
        $estimate = Estimate::firstOrCreate(['id_application' => $app->id]);

        if ($request->has('repair_sum') || $request->has('defect_sum')) {
            $estimate->ic_repair_sum = (float) $request->input('repair_sum', 0);
            $estimate->ic_defect_sum = (float) $request->input('defect_sum', 0);
        }

        if ($request->has('single_sum')) {
            $sum = (float) $request->input('single_sum');
            if ($app->ub_type == 1) {
                $estimate->summ_ic_half = $sum;
                $estimate->price_repair_half = $sum;
            } else {
                $estimate->summ_ic_full = $sum;
                $estimate->price_repair_full = $sum;
            }
        }

        $estimate->save();

        return response()->json([
            'success' => true,
            'summary' => $this->financeService->calculate($app, $request->user()),
        ]);
    }

    // Обновление параметров ДДС (доход СК, даты, факт. расчет СТО)
    public function updateDds(int $id, Request $request): JsonResponse
    {
        $app = Application::findOrFail($id);
        $estimate = Estimate::firstOrCreate(['id_application' => $app->id]);

        if ($request->has('dds_income_sum')) {
            $estimate->dds_income_sum = (float) $request->input('dds_income_sum');
        }
        if ($request->has('dds_income_date')) {
            $estimate->dds_income_date = $request->input('dds_income_date');
        }
        if ($request->has('dds_sto_pay_date')) {
            $estimate->dds_sto_pay_date = $request->input('dds_sto_pay_date');
            $app->sto_pay_date = $request->input('dds_sto_pay_date');
            $app->save();
        }

        $estimate->save();

        return response()->json([
            'success' => true,
            'summary' => $this->financeService->calculate($app, $request->user()),
        ]);
    }

    // Установка процентов ЗП для дела (только владелец / админ)
    public function updateZpPercentages(int $id, Request $request): JsonResponse
    {
        $app = Application::findOrFail($id);

        if ($request->has('mrk_proc')) {
            $app->mrk_proc_custom = $request->input('mrk_proc') !== null ? (float) $request->input('mrk_proc') : null;
        }
        if ($request->has('mentor_proc')) {
            $app->mentor_proc_custom = $request->input('mentor_proc') !== null ? (float) $request->input('mentor_proc') : null;
        }
        $app->save();

        return response()->json([
            'success' => true,
            'summary' => $this->financeService->calculate($app, $request->user()),
        ]);
    }
}