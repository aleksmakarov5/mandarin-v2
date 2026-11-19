<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\SparePart;
use App\Models\Work;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstimateController extends Controller
{
    /**
     * Получение всех данных сметы по заявке
     */
    public function show(int $applicationId): JsonResponse
    {
        $app = Application::findOrFail($applicationId);

        $spareParts = SparePart::where('application_id', $applicationId)
            ->orderBy('id', 'asc')
            ->get();

        $repairWorks = Work::where('application_id', $applicationId)
            ->where('type', 'repair')
            ->orderBy('id', 'asc')
            ->get();

        $paintWorks = Work::where('application_id', $applicationId)
            ->where('type', 'paint')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'spare_parts' => $spareParts,
            'repair_works' => $repairWorks,
            'paint_works' => $paintWorks,
            'summary' => [
                'sum_sk' => (float) ($app->sum_sk ?? 0),
                'sum_zap' => (float) ($app->sum_zap ?? 0),
                'remont_sum' => (float) ($app->remont_sum ?? 0),
            ]
        ]);
    }

    /**
     * Добавление новой запчасти
     */
    public function storeSparePart(Request $request, int $applicationId): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'count' => ['required', 'numeric', 'min:1'],
            'price_sk' => ['nullable', 'numeric', 'min:0'],
            'sto_price' => ['nullable', 'numeric', 'min:0'],
            'orig_price' => ['nullable', 'numeric', 'min:0'],
            'anal_price' => ['nullable', 'numeric', 'min:0'],
            'bu_price' => ['nullable', 'numeric', 'min:0'],
            'buy_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:50'],
            'term' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['application_id'] = $applicationId;
        $part = SparePart::create($validated);

        $this->recalculateApplicationSums($applicationId);

        return response()->json($part, 201);
    }

    /**
     * Обновление строки запчасти (включая выбор закупки в 1 клик)
     */
    public function updateSparePart(Request $request, int $applicationId, int $partId): JsonResponse
    {
        $part = SparePart::where('application_id', $applicationId)->findOrFail($partId);

        $data = $request->only([
            'name',
            'part_number',
            'count',
            'price_sk',
            'sto_price',
            'orig_price',
            'anal_price',
            'bu_price',
            'buy_price',
            'status',
            'term'
        ]);

        $part->update($data);

        $this->recalculateApplicationSums($applicationId);

        return response()->json($part);
    }

    /**
     * Удаление запчасти
     */
    public function destroySparePart(int $applicationId, int $partId): JsonResponse
    {
        $part = SparePart::where('application_id', $applicationId)->findOrFail($partId);
        $part->delete();

        $this->recalculateApplicationSums($applicationId);

        return response()->json(['message' => 'Запчасть удалена']);
    }

    /**
     * Добавление работы (слесарная или окрасочная)
     */
    public function storeWork(Request $request, int $applicationId): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:repair,paint'],
            'name' => ['required', 'string', 'max:255'],
            'norm_hour' => ['required', 'numeric', 'min:0'],
            'cost_hour' => ['required', 'numeric', 'min:0'],
            'paint_cost' => ['nullable', 'numeric', 'min:0'], // для окраски (материалы)
            'sto_hour' => ['nullable', 'numeric', 'min:0'],
            'sto_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['application_id'] = $applicationId;
        $work = Work::create($validated);

        $this->recalculateApplicationSums($applicationId);

        return response()->json($work, 201);
    }

    /**
     * Обновление работы
     */
    public function updateWork(Request $request, int $applicationId, int $workId): JsonResponse
    {
        $work = Work::where('application_id', $applicationId)->findOrFail($workId);

        $work->update($request->only([
            'name',
            'norm_hour',
            'cost_hour',
            'paint_cost',
            'sto_hour',
            'sto_cost'
        ]));

        $this->recalculateApplicationSums($applicationId);

        return response()->json($work);
    }

    /**
     * Удаление работы
     */
    public function destroyWork(int $applicationId, int $workId): JsonResponse
    {
        $work = Work::where('application_id', $applicationId)->findOrFail($workId);
        $work->delete();

        $this->recalculateApplicationSums($applicationId);

        return response()->json(['message' => 'Работа удалена']);
    }

    /**
     * Автоматический пересчет итоговых сумм в таблице applications
     */
    private function recalculateApplicationSums(int $applicationId): void
    {
        $app = Application::find($applicationId);
        if (!$app)
            return;

        // Себестоимость купленных деталей
        $sumZap = SparePart::where('application_id', $applicationId)
            ->sum(DB::raw('COALESCE(buy_price, 0) * COALESCE(count, 1)'));

        // Себестоимость ремонтных и окрасочных работ
        $repairSum = Work::where('application_id', $applicationId)
            ->sum(DB::raw('(COALESCE(norm_hour, 0) * COALESCE(cost_hour, 0)) + COALESCE(paint_cost, 0)'));

        $app->update([
            'sum_zap' => $sumZap,
            'remont_sum' => $repairSum,
        ]);
    }
}