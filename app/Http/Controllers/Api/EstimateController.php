<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Work;
use App\Models\FactWork;
use App\Models\ExtraExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EstimateWorksController extends Controller
{
    // Определение типа автора
    protected function resolveAuthorType($user, $app): int
    {
        if (!$user)
            return 0;
        $uid = (int) $user->id;
        $gid = (int) ($user->GroupId ?? $user->user_group_id ?? 0);

        if ($app) {
            $mrkId = (int) ($app->id_user ?? $app->mrk_id ?? 0);
            $mentorId = (int) ($app->mentor_id ?? 0);
            $serviceUserId = (int) ($app->service_user_id ?? 0);

            if ($mrkId && $uid === $mrkId)
                return 1;
            if ($mentorId && $uid === $mentorId)
                return 2;
            if ($serviceUserId && $uid === $serviceUserId)
                return 4;
        }

        if ($gid === 8 || ($user->is_service ?? false))
            return 4;
        if ($gid === 5)
            return 1;
        if ($gid === 10)
            return 2;

        return 5;
    }

    // =========================================================================
    // 1. РЕМОНТНЫЕ И ОКРАСОЧНЫЕ РАБОТЫ (ИЗ КАЛЬКУЛЯЦИИ)
    // =========================================================================

    public function getCalcWorks(int $applicationId): JsonResponse
    {
        $works = Work::where('application_id', $applicationId)
            ->orderBy('id', 'asc')
            ->get();

        $repair = $works->where('work_category', 'repair')->values();
        $paint = $works->where('work_category', 'paint')->values();

        return response()->json([
            'repair' => $repair,
            'paint' => $paint,
            'totals' => [
                'repair_ic' => $repair->sum('sum_ic'),
                'repair_pzn' => $repair->sum('sum_pzn'),
                'paint_ic' => $paint->sum('sum_ic'),
                'paint_pzn' => $paint->sum('sum_pzn'),
            ]
        ]);
    }

    public function storeCalcWork(Request $request, int $applicationId): JsonResponse
    {
        $category = $request->input('work_category', 'repair');
        $user = Auth::user();
        $app = Application::findOrFail($applicationId);

        $work = Work::create([
            'application_id' => $applicationId,
            'work_category' => $category,
            'name' => $request->input('name', $category === 'repair' ? 'Новая слесарная работа' : 'Окраска детали'),
            'norm_hours' => (float) $request->input('norm_hours', 1),
            'pzn_hours' => (float) $request->input('pzn_hours', 1),
            'price_per_hour' => (float) $request->input('price_per_hour', 1500),
            'sum_materials' => (float) $request->input('sum_materials', 0),
            'sum_ic' => (float) $request->input('sum_ic', 1500),
            'sum_pzn' => (float) $request->input('sum_pzn', 1500),
            'author_type' => $this->resolveAuthorType($user, $app),
        ]);

        return response()->json(['data' => $work], 201);
    }

    public function updateCalcWorkField(Request $request, int $applicationId, int $id): JsonResponse
    {
        $work = Work::where('application_id', $applicationId)->findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');

        if (in_array($field, ['norm_hours', 'pzn_hours', 'price_per_hour', 'sum_materials', 'sum_ic', 'sum_pzn'])) {
            $value = (float) str_replace([' ', ','], ['', '.'], $value);
        }

        $work->{$field} = $value;

        // Автопересчет сумм
        if (in_array($field, ['norm_hours', 'pzn_hours', 'price_per_hour', 'sum_materials'])) {
            $laborIc = (float) $work->norm_hours * (float) $work->price_per_hour;
            $laborPzn = (float) $work->pzn_hours * (float) $work->price_per_hour;

            if ($work->work_category === 'paint') {
                $work->sum_ic = $laborIc + (float) $work->sum_materials;
                $work->sum_pzn = $laborPzn + (float) $work->sum_materials;
            } else {
                $work->sum_ic = $laborIc;
                $work->sum_pzn = $laborPzn;
            }
        }

        $user = Auth::user();
        $app = Application::find($applicationId);
        $work->author_type = $this->resolveAuthorType($user, $app);
        $work->save();

        return response()->json(['data' => $work]);
    }

    // Перевод работы в категорию «Окраска» (аналог makePaint из старой системы)
    public function toggleCategory(int $applicationId, int $id): JsonResponse
    {
        $work = Work::where('application_id', $applicationId)->findOrFail($id);
        $work->work_category = $work->work_category === 'repair' ? 'paint' : 'repair';
        $work->save();

        return response()->json(['data' => $work]);
    }

    public function destroyCalcWork(int $applicationId, int $id): JsonResponse
    {
        Work::where('application_id', $applicationId)->where('id', $id)->delete();
        return response()->json(['status' => 'ok']);
    }

    // =========================================================================
    // 2. ФАКТИЧЕСКИЕ РАБОТЫ (АКТ ВЫПОЛНЕННЫХ РАБОТ СТО)
    // =========================================================================

    public function getFactWorks(int $applicationId): JsonResponse
    {
        $facts = FactWork::where('application_id', $applicationId)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => $facts,
            'total_sum' => $facts->sum('price_service')
        ]);
    }

    public function storeFactWork(Request $request, int $applicationId): JsonResponse
    {
        $user = Auth::user();
        $app = Application::findOrFail($applicationId);

        $fact = FactWork::create([
            'application_id' => $applicationId,
            'name' => $request->input('name', 'Фактическая работа СТО'),
            'price_service' => (float) $request->input('price_service', 0),
            'execution_date' => $request->input('execution_date', now()->toDateString()),
            'author_type' => $this->resolveAuthorType($user, $app),
            'created_by_user_id' => $user?->id,
            'updated_by_user_id' => $user?->id,
        ]);

        return response()->json(['data' => $fact], 201);
    }

    public function updateFactWorkField(Request $request, int $applicationId, int $id): JsonResponse
    {
        $fact = FactWork::where('application_id', $applicationId)->findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');

        if ($field === 'price_service') {
            $value = (float) str_replace([' ', ','], ['', '.'], $value);
        }

        $user = Auth::user();
        $app = Application::find($applicationId);

        $fact->{$field} = $value;
        $fact->author_type = $this->resolveAuthorType($user, $app);
        $fact->updated_by_user_id = $user?->id;
        $fact->save();

        return response()->json(['data' => $fact]);
    }

    public function destroyFactWork(int $applicationId, int $id): JsonResponse
    {
        FactWork::where('application_id', $applicationId)->where('id', $id)->delete();
        return response()->json(['status' => 'ok']);
    }

    // =========================================================================
    // 3. ДОПОЛНИТЕЛЬНЫЕ ЗАТРАТЫ (ЭВАКУАТОР, МОЙКА, ТАКСИ, СЕРТИФИКАТЫ)
    // =========================================================================

    public function getExtraExpenses(int $applicationId): JsonResponse
    {
        $expenses = ExtraExpense::where('application_id', $applicationId)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => $expenses,
            'total_sum' => $expenses->sum('sum')
        ]);
    }

    public function storeExtraExpense(Request $request, int $applicationId): JsonResponse
    {
        $user = Auth::user();
        $app = Application::findOrFail($applicationId);

        $expense = ExtraExpense::create([
            'application_id' => $applicationId,
            'name' => $request->input('name', 'Эвакуатор'),
            'expense_type' => $request->input('expense_type', 'эвакуатор'),
            'sum' => (float) $request->input('sum', 0),
            'comment' => $request->input('comment', ''),
            'author_type' => $this->resolveAuthorType($user, $app),
            'created_by_user_id' => $user?->id,
            'updated_by_user_id' => $user?->id,
        ]);

        return response()->json(['data' => $expense], 201);
    }

    public function updateExtraExpenseField(Request $request, int $applicationId, int $id): JsonResponse
    {
        $expense = ExtraExpense::where('application_id', $applicationId)->findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');

        if ($field === 'sum') {
            $value = (float) str_replace([' ', ','], ['', '.'], $value);
        }

        $user = Auth::user();
        $app = Application::find($applicationId);

        $expense->{$field} = $value;
        $expense->author_type = $this->resolveAuthorType($user, $app);
        $expense->updated_by_user_id = $user?->id;
        $expense->save();

        return response()->json(['data' => $expense]);
    }

    public function destroyExtraExpense(int $applicationId, int $id): JsonResponse
    {
        ExtraExpense::where('application_id', $applicationId)->where('id', $id)->delete();
        return response()->json(['status' => 'ok']);
    }
}