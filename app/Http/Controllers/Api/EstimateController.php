<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EstimateController extends Controller
{
    /**
     * Определение колонки привязки к делу в таблице estimates
     */
    protected function getAppIdColumn(): string
    {
        $cols = Schema::getColumnListing('estimates');
        return in_array('application_id', $cols, true) ? 'application_id' : 'id_application';
    }

    /**
     * Получить сметные данные калькуляции по ID дела
     */
    public function showByApplication(int $applicationId): JsonResponse
    {
        $appCol = $this->getAppIdColumn();

        $estimate = Estimate::where($appCol, $applicationId)->first();

        if (!$estimate) {
            $estimate = Estimate::create([
                $appCol => $applicationId,
                'price_sp_full' => 0,
                'price_sp_half' => 0,
                'price_repair' => 0,
                'price_paint' => 0,
                'price_zip' => 0,
                'price_work' => 0,
                'price_repair_half' => 0,
                'price_repair_full' => 0,
                'add_pay' => 0,
                'price_repair_full_md2' => 0,
                'fact' => 0,
            ]);
        }

        return response()->json($estimate);
    }

    /**
     * Сохранение / обновление итоговых данных калькуляции
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $appCol = $this->getAppIdColumn();
            $applicationId = (int) ($request->input('id_application') ?? $request->input('application_id') ?? $id);

            // Ищем по прямому id записи, либо по id дела
            $estimate = Estimate::where('id', $id)->first();
            if (!$estimate && $applicationId > 0) {
                $estimate = Estimate::where($appCol, $applicationId)->first();
            }

            if (!$estimate) {
                $estimate = new Estimate();
                $estimate->{$appCol} = $applicationId;
            }

            $fields = [
                'price_sp_full',
                'price_sp_half',
                'price_repair',
                'price_paint',
                'price_zip',
                'price_work',
                'price_repair_half',
                'price_repair_full',
                'add_pay',
                'price_repair_full_md2',
            ];

            $cols = Schema::getColumnListing('estimates');

            foreach ($fields as $field) {
                if ($request->has($field) && in_array($field, $cols, true)) {
                    $raw = $request->input($field);
                    $clean = is_numeric($raw)
                        ? (float) $raw
                        : (float) str_replace([' ', ','], ['', '.'], (string) $raw);
                    $estimate->{$field} = $clean;
                }
            }

            // Пересчет факта из фактических работ, затрат и выбранных запчастей
            $factSum = $this->calculateActualFact($applicationId);
            if (in_array('fact', $cols, true)) {
                $estimate->fact = $factSum;
            }

            $estimate->save();

            return response()->json($estimate->fresh());
        } catch (\Throwable $e) {
            Log::error('Ошибка сохранения калькуляции: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Не удалось сохранить данные калькуляции: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Подсчет фактических затрат по делу
     */
    protected function calculateActualFact(int $applicationId): float
    {
        $fact = 0.0;

        // 1. Фактические работы
        if (Schema::hasTable('fact_works')) {
            $factWorksAppCol = Schema::hasColumn('fact_works', 'application_id') ? 'application_id' : 'id_application';
            $fact += (float) DB::table('fact_works')
                ->where($factWorksAppCol, $applicationId)
                ->sum('price_service');
        }

        // 2. Дополнительные затраты
        if (Schema::hasTable('extra_expenses')) {
            $extraAppCol = Schema::hasColumn('extra_expenses', 'application_id') ? 'application_id' : 'id_application';
            $fact += (float) DB::table('extra_expenses')
                ->where($extraAppCol, $applicationId)
                ->sum('sum');
        }

        // 3. Выбранные закупки запчастей
        if (Schema::hasTable('spare_parts')) {
            $spAppCol = Schema::hasColumn('spare_parts', 'application_id') ? 'application_id' : 'id_application';
            $parts = DB::table('spare_parts')->where($spAppCol, $applicationId)->get();

            foreach ($parts as $p) {
                $mrk = $p->selected_mrk ?? null;
                $choice = (int) ($p->selected_price ?? 0);

                if ($mrk === 'original' || $choice === 1) {
                    $fact += (float) ($p->price_original ?? 0);
                } elseif ($mrk === 'analogue' || $choice === 2) {
                    $fact += (float) ($p->price_analogue ?? 0);
                } elseif ($mrk === 'used' || $choice === 3) {
                    $fact += (float) ($p->price_used ?? 0);
                } elseif ($mrk === 'sto' || $choice === 4) {
                    $fact += (float) ($p->price_sto ?? 0);
                }
            }
        }

        return round($fact, 2);
    }
}