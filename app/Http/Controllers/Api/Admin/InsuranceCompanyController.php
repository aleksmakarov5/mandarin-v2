<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsuranceCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsuranceCompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InsuranceCompany::query();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where('name', 'LIKE', "%{$s}%")
                ->orWhere('curator', 'LIKE', "%{$s}%")
                ->orWhere('email', 'LIKE', "%{$s}%")
                ->orWhere('phone', 'LIKE', "%{$s}%");
        }

        $items = $query->orderBy('name')->get();
        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'curator' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        $item = InsuranceCompany::create($validated);
        return response()->json($item, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(InsuranceCompany::findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = InsuranceCompany::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'curator' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    public function destroy(int $id): JsonResponse
    {
        InsuranceCompany::findOrFail($id)->delete();
        return response()->json(['message' => 'Страховая компания удалена']);
    }
}