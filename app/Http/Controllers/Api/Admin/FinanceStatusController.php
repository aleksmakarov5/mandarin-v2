<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FinanceStatus::withCount('applications');

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . trim($request->search) . '%');
        }

        $items = $query->orderBy('id', 'asc')->get();
        return response()->json($items);
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

        $status = FinanceStatus::create($validated);
        return response()->json($status, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(FinanceStatus::withCount('applications')->findOrFail($id));
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
        return response()->json($status);
    }

    public function destroy(int $id): JsonResponse
    {
        $status = FinanceStatus::withCount('applications')->findOrFail($id);

        if ($status->applications_count > 0) {
            return response()->json([
                'message' => 'Невозможно удалить фин. статус, привязанный к существующим заявкам'
            ], 422);
        }

        $status->delete();
        return response()->json(['message' => 'Фин. статус удален']);
    }
}