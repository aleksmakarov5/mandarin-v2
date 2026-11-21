<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ExtraExpense;
use App\Models\ExtraExpenseHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExtraExpenseController extends Controller
{
    /**
     * Определение типа автора для цветовой маркировки:
     * 0 - авто/калькуляция, 1 - МРК, 2 - Наставник, 4 - Сервис, 5 - Другой
     */
    protected function resolveAuthorType($user, ?Application $app): int
    {
        if (!$user)
            return 0;
        $uid = (int) $user->id;
        $gid = (int) ($user->GroupId ?? $user->user_group_id ?? 0);

        if ($app) {
            $mrkId = (int) ($app->id_MRK ?? $app->id_user ?? 0);
            $mentorId = (int) ($app->id_spm ?? $app->mentor_id ?? 0);

            if ($mrkId && $uid === $mrkId)
                return 1;    // Синий: МРК дела
            if ($mentorId && $uid === $mentorId)
                return 2;// Сиреневый: Наставник
        }

        if ($gid === 5)
            return 1;  // МРК
        if ($gid === 10)
            return 2; // Наставник
        if ($gid === 8 || ($user->is_service ?? false))
            return 4; // Сервис

        return 5; // Коричневый: другой пользователь
    }

    public function index(Request $request, int $applicationId): JsonResponse
    {
        $user = $request->user();

        // Защита: СТО/сервису и запчастистам доступ закрыт
        if (($user->is_service ?? false) || in_array((int) ($user->GroupId ?? $user->user_group_id), [8, 9])) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $items = ExtraExpense::with(['creator:id,name', 'updater:id,name'])
            ->where('application_id', $applicationId)
            ->orderBy('id', 'asc')
            ->get();

        $totalSum = (float) $items->sum('sum');

        return response()->json([
            'data' => $items,
            'total_sum' => $totalSum,
        ]);
    }

    public function store(Request $request, int $applicationId): JsonResponse
    {
        $user = $request->user();
        if (($user->is_service ?? false) || in_array((int) ($user->GroupId ?? $user->user_group_id), [8, 9])) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $app = Application::findOrFail($applicationId);
        $authorType = $this->resolveAuthorType($user, $app);

        $expense = ExtraExpense::create([
            'application_id' => $applicationId,
            'name' => $request->input('name', 'Эвакуатор'),
            'expense_type' => $request->input('expense_type', 'эвакуатор'),
            'sum' => (float) $request->input('sum', 0),
            'comment' => $request->input('comment', ''),
            'author_type' => $authorType,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        $this->logHistory($expense, 'created', null, null, "Добавлена статья: {$expense->name} на сумму {$expense->sum} ₽");

        return response()->json([
            'data' => $expense->load(['creator:id,name', 'updater:id,name'])
        ], 201);
    }

    public function updateField(Request $request, int $applicationId, int $id): JsonResponse
    {
        $user = $request->user();
        if (($user->is_service ?? false) || in_array((int) ($user->GroupId ?? $user->user_group_id), [8, 9])) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $expense = ExtraExpense::where('application_id', $applicationId)->findOrFail($id);
        $field = $request->input('field');
        $newValue = $request->input('value');
        $oldValue = $expense->{$field};

        if ($field === 'sum') {
            $newValue = (float) str_replace([' ', ','], ['', '.'], $newValue);
        }

        if ($oldValue == $newValue) {
            return response()->json(['data' => $expense]);
        }

        $app = Application::find($applicationId);
        $expense->{$field} = $newValue;
        $expense->author_type = $this->resolveAuthorType($user, $app);
        $expense->updated_by_user_id = $user->id;
        $expense->save();

        $this->logHistory($expense, 'updated', $field, (string) $oldValue, (string) $newValue);

        return response()->json([
            'data' => $expense->load(['creator:id,name', 'updater:id,name'])
        ]);
    }

    public function destroy(Request $request, int $applicationId, int $id): JsonResponse
    {
        $user = $request->user();
        if (($user->is_service ?? false) || in_array((int) ($user->GroupId ?? $user->user_group_id), [8, 9])) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $expense = ExtraExpense::where('application_id', $applicationId)->findOrFail($id);
        $name = $expense->name;
        $sum = $expense->sum;

        $this->logHistory($expense, 'deleted', null, "{$name} ({$sum} ₽)", null);
        $expense->delete();

        return response()->json(['message' => 'Статья затрат удалена']);
    }

    public function history(Request $request, int $applicationId): JsonResponse
    {
        $history = ExtraExpenseHistory::with('user:id,name')
            ->where('application_id', $applicationId)
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        return response()->json(['data' => $history]);
    }

    protected function logHistory(ExtraExpense $expense, string $action, ?string $field, ?string $old, ?string $new): void
    {
        ExtraExpenseHistory::create([
            'extra_expense_id' => $expense->id,
            'application_id' => $expense->application_id,
            'user_id' => Auth::id(),
            'action_type' => $action,
            'field_name' => $field,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}