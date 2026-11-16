<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = City::query();

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . trim($request->search) . '%');
        }

        $items = $query->orderBy('name')->get();
        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:cities,name',
        ]);

        $city = City::create($validated);
        return response()->json($city, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $city = City::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:cities,name,' . $city->id,
        ]);

        $city->update($validated);
        return response()->json($city);
    }

    public function destroy(int $id): JsonResponse
    {
        City::findOrFail($id)->delete();
        return response()->json(['message' => 'Город удален']);
    }
}