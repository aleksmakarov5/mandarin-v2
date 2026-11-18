<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class FinanceStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FinanceStatus::query();

            // Безопасный подсчет количества заявок с проверкой связи
            try {
                $query->withCount('applications');
            } catch (\Throwable $e) {
                // Если связь не настроена — не роняем весь запрос
            }

            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where('name', 'LIKE', "%{$s}%");
            }

            $items = $query->orderBy('id', 'asc')->get();

            // Нормализация полей для фронтенда
            $items->transform(function ($item) {
                $item->bg_color = $item->bg_color ?: ($item->color ?: '#e0f2fe');
                $item->text_color = $item->text_color ?: '#0369a1';
                $item->time = (int) ($item->time ?? $item->fin_time ?? 0);
                $item->is_day = (bool) ($item->is_day ?? $item->fin_day ?? $item->day ?? false);
                $item->applications_count = $item->applications_count ?? 0;
                return $item;
            });

            // Возвращаем и плоский массив, и в ключе data для совместимости с любым Vue-кодом
            return response()->json([
                'data' => $items,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка FinanceStatusController::index: ' . $e->getMessage());

            // Если через Eloquent не вышло, пробуем прямой запрос к таблице
            if (Schema::hasTable('finance_statuses')) {
                $raw = DB::table('finance_statuses')->orderBy('id')->get();
                return response()->json(['data' => $raw]);
            }

            return response()->json(['data' => []]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'bg_color' => 'nullable|string|max:30',
            'text_color' => 'nullable|string|max:30',
            'time' => 'nullable|integer|min:0',
            'is_day' => 'nullable|boolean',
        ]);

        $status = FinanceStatus::create([
            'name' => trim($validated['name']),
            'color' => $validated['color'] ?? 'secondary',
            'bg_color' => $validated['bg_color'] ?? '#e0f2fe',
            'text_color' => $validated['text_color'] ?? '#0369a1',
            'time' => $validated['time'] ?? 0,
            'is_day' => $validated['is_day'] ?? false,
        ]);

        return response()->json([
            'message' => 'Финансовый статус создан',
            'data' => $status,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $status = FinanceStatus::findOrFail($id);
        return response()->json(['data' => $status]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $status = FinanceStatus::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'bg_color' => 'nullable|string|max:30',
            'text_color' => 'nullable|string|max:30',
            'time' => 'nullable|integer|min:0',
            'is_day' => 'nullable|boolean',
        ]);

        $status->update($validated);

        return response()->json([
            'message' => 'Финансовый статус обновлен',
            'data' => $status,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $status = FinanceStatus::findOrFail($id);

        // Проверяем наличие привязанных заявок по всем возможным колонкам
        $hasApps = false;
        if (Schema::hasTable('applications')) {
            $hasApps = DB::table('applications')
                ->where('finance_status_id', $id)
                ->orWhere('id_finance_status', $id)
                ->exists();
        }

        if ($hasApps) {
            return response()->json([
                'message' => 'Невозможно удалить фин. статус: к нему привязаны существующие заявки'
            ], 422);
        }

        $status->delete();
        return response()->json(['message' => 'Фин. статус удален']);
    }
}