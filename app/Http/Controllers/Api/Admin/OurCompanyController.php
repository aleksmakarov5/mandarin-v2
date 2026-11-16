<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OurCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OurCompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OurCompany::with('legalEntity');

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                    ->orWhere('email', 'LIKE', "%{$s}%")
                    ->orWhere('inn', 'LIKE', "%{$s}%")
                    ->orWhere('phone', 'LIKE', "%{$s}%");
            });
        }

        $items = $query->orderBy('name', 'asc')->get();

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mailer' => 'nullable|string|max:255',
            'id_legal' => 'nullable|exists:legal_entities,id',
            'inn' => 'nullable|string|max:30',
            'kpp' => 'nullable|string|max:30',
            'ogrn' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'director' => 'nullable|string|max:255',
            'sign' => 'nullable|string',
            'signature_path' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Укажите название филиала.',
            'email.required' => 'Укажите email филиала.',
            'email.email' => 'Укажите корректный адрес электронной почты.',
        ]);

        $item = OurCompany::create($validated);

        return response()->json($item->load('legalEntity'), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(OurCompany::with('legalEntity')->findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = OurCompany::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mailer' => 'nullable|string|max:255',
            'id_legal' => 'nullable|exists:legal_entities,id',
            'inn' => 'nullable|string|max:30',
            'kpp' => 'nullable|string|max:30',
            'ogrn' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'director' => 'nullable|string|max:255',
            'sign' => 'nullable|string',
            'signature_path' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Укажите название филиала.',
            'email.required' => 'Укажите email филиала.',
        ]);

        $item->update($validated);

        return response()->json($item->load('legalEntity'));
    }

    public function destroy(int $id): JsonResponse
    {
        $item = OurCompany::findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Филиал успешно удален']);
    }
}