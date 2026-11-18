<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ApplicationStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ApplicationStatus::query()->with('group');

            // Безопасная попытка подсчета заявок
            try {
                $query->withCount('applications');
            } catch (\Throwable $e) {
                // Если связь не настроена или ключ другой — не роняем запрос
            }

            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where('name', 'LIKE', "%{$s}%");
            }

            $items = $query->orderBy('id', 'asc')->get();

            // Нормализация полей для фронтенда
            $items->transform(function ($item) {
                $item->bg_color = $item->bg_color ?: ($item->color ?: '#fef08a');
                $item->text_color = $item->text_color ?: '#854d0e';
                $item->time = (int) ($item->time ?? 0);
                $item->is_day = (bool) ($item->is_day ?? $item->day ?? true);
                $item->applications_count = $item->applications_count ?? 0;
                return $item;
            });

            return response()->json([
                'data' => $items,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка ApplicationStatusController@index: ' . $e->getMessage());

            // Резервный прямой SQL-запрос при сбое Eloquent
            if (Schema::hasTable('application_statuses')) {
                $raw = DB::table('application_statuses')->orderBy('id', 'asc')->get();
                return response()->json(['data' => $raw]);
            }

            return response()->json(['data' => []]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'bg_color' => 'nullable|string|max:30',
            'text_color' => 'nullable|string|max:30',
            'time' => 'nullable|integer|min:0',
            'is_day' => 'nullable|boolean',
            'user_group_id' => 'nullable|exists:user_groups,id',
        ]);

        $status = ApplicationStatus::create([
            'name' => trim($validated['name']),
            'code' => $validated['code'] ?? null,
            'color' => $validated['color'] ?? 'secondary',
            'bg_color' => $validated['bg_color'] ?? '#fef08a',
            'text_color' => $validated['text_color'] ?? '#854d0e',
            'time' => $validated['time'] ?? 0,
            'is_day' => $validated['is_day'] ?? true,
            'user_group_id' => $validated['user_group_id'] ?? null,
        ]);

        return response()->json(['data' => $status->load('group')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $status = ApplicationStatus::with('group')->findOrFail($id);
        return response()->json(['data' => $status]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $status = ApplicationStatus::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'bg_color' => 'nullable|string|max:30',
            'text_color' => 'nullable|string|max:30',
            'time' => 'nullable|integer|min:0',
            'is_day' => 'nullable|boolean',
            'user_group_id' => 'nullable|exists:user_groups,id',
        ]);

        $status->update($validated);

        return response()->json(['data' => $status->fresh('group')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $status = ApplicationStatus::findOrFail($id);

        // Проверяем наличие заявок
        $hasApps = false;
        if (Schema::hasTable('applications')) {
            $hasApps = DB::table('applications')
                ->where('id_status', $id)
                ->orWhere('status_id', $id)
                ->exists();
        }

        if ($hasApps) {
            return response()->json([
                'message' => 'Невозможно удалить статус, привязанный к существующим заявкам'
            ], 422);
        }

        $status->delete();
        return response()->json(['message' => 'Статус удален']);
    }
}