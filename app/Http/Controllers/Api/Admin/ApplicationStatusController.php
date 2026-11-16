<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationStatusController extends Controller
{
    public function index(): JsonResponse
    {
        $statuses = ApplicationStatus::with('group')
            ->withCount('applications')
            ->orderBy('id')
            ->get();

        return response()->json($statuses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'user_group_id' => 'nullable|exists:user_groups,id',
            'color' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
        ]);

        $status = ApplicationStatus::create($validated);

        return response()->json($status->load('group'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $status = ApplicationStatus::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'user_group_id' => 'nullable|exists:user_groups,id',
            'color' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
        ]);

        $status->update($validated);

        return response()->json($status->fresh('group'));
    }

    public function destroy(int $id): JsonResponse
    {
        $status = ApplicationStatus::withCount('applications')->findOrFail($id);

        if ($status->applications_count > 0) {
            return response()->json([
                'message' => 'Невозможно удалить статус, привязанный к существующим заявкам'
            ], 422);
        }

        $status->delete();

        return response()->json(['message' => 'Статус успешно удален']);
    }
}