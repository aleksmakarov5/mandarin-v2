<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Используем withDefault в модели или безопасный load, сортируем по id
        $statuses = ApplicationStatus::with('group')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($statuses);
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
            'user_group_id' => 'nullable|exists:user_groups,id',
        ]);

        $validated['color'] = $validated['color'] ?? 'secondary';
        $validated['time'] = $validated['time'] ?? 0;
        $validated['is_day'] = $validated['is_day'] ?? false;

        $status = ApplicationStatus::create($validated);

        return response()->json($status->load('group'), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(ApplicationStatus::with('group')->findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $status = ApplicationStatus::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'bg_color' => 'nullable|string|max:30',
            'text_color' => 'nullable|string|max:30',
            'time' => 'nullable|integer|min:0',
            'is_day' => 'nullable|boolean',
            'user_group_id' => 'nullable|exists:user_groups,id',
        ]);

        $status->update($validated);

        return response()->json($status->load('group'));
    }

    public function destroy(int $id): JsonResponse
    {
        ApplicationStatus::findOrFail($id)->delete();
        return response()->json(['message' => 'Статус успешно удален']);
    }
}