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
     * Получение сводных данных сметы и ролей текущего пользователя для карточки дела
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $application = \App\Models\Application::findOrFail($id);
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            // 1. Точное определение роли пользователя на основе методов модели User
            $isSto = false;
            $isSpm = false;
            $isMrk = false;

            if ($user) {
                if (method_exists($user, 'isSto')) {
                    $isSto = $user->isSto();
                } else {
                    $isSto = (int) ($user->user_group_id ?? 0) === 8 || ($user->is_service ?? false);
                }

                if (method_exists($user, 'isSpm')) {
                    $isSpm = $user->isSpm();
                } else {
                    $isSpm = in_array((int) ($user->user_group_id ?? 0), [7, 9], true);
                }

                if (method_exists($user, 'isMrk')) {
                    $isMrk = $user->isMrk();
                } else {
                    $isMrk = (int) ($user->user_group_id ?? 0) === 5;
                }
            }

            // Роль в виде строки для фронтенда
            $userRole = 'mrk';
            if ($isSto) {
                $userRole = 'service';
            } elseif ($isSpm) {
                $userRole = 'spm';
            } elseif ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
                $userRole = 'admin';
            }

            // 2. Получение данных сервиса, привязанного к делу
            $serviceId = $application->id_service ?? $application->service_id ?? null;
            $serviceName = '';
            if ($serviceId && \Illuminate\Support\Facades\Schema::hasTable('services')) {
                $serviceRow = \Illuminate\Support\Facades\DB::table('services')
                    ->where('id', $serviceId)
                    ->first(['id', 'name']);
                if ($serviceRow) {
                    $serviceName = (string) $serviceRow->name;
                }
            }

            // 3. Подсчет сумм для KPI сметы
            $sumSk = (float) ($application->sum_sk ?? 0);
            $sumZap = (float) ($application->sum_zap ?? 0);
            $remontSum = (float) ($application->remont_sum ?? 0);
            $totalCosts = $sumZap + $remontSum;
            $remains = max(0, $sumSk - $totalCosts);
            $marginPercent = $sumSk > 0 ? (int) round(($remains / $sumSk) * 100) : 0;

            return response()->json([
                'status' => 'ok',
                'application_id' => $application->id,
                'role' => $userRole,
                'is_sto' => $isSto,
                'is_spm' => $isSpm,
                'is_mrk' => $isMrk,
                'current_user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name ?? null,
                    'full_name' => method_exists($user, 'getFullNameAttribute')
                        ? $user->full_name
                        : trim(($user->last_name ?? '') . ' ' . $user->name),
                ] : null,
                'service' => [
                    'id' => $serviceId ? (int) $serviceId : null,
                    'name' => $serviceName,
                ],
                'summary' => [
                    'sum_sk' => $sumSk,
                    'sum_zap' => $sumZap,
                    'remont_sum' => $remontSum,
                    'total_costs' => $totalCosts,
                    'remains' => $remains,
                    'margin_percent' => $marginPercent,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => "Дело #{$id} не найдено"], 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Ошибка в EstimateController::show({$id}): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Ошибка загрузки сметы: ' . $e->getMessage(),
            ], 500);
        }
    }
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