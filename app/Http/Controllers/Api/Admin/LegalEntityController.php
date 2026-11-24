<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalEntity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalEntityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LegalEntity::query();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where('name', 'LIKE', "%{$s}%")
                ->orWhere('inn', 'LIKE', "%{$s}%")
                ->orWhere('kpp', 'LIKE', "%{$s}%");
        }

        $items = $query->orderBy('name')->get();
        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'full_name' => 'nullable|string|max:500',
            'inn' => 'nullable|string|max:20',
            'kpp' => 'nullable|string|max:20',
            'ogrn' => 'nullable|string|max:30',
            'legal_address' => 'nullable|string|max:500',
            'actual_address' => 'nullable|string|max:500',
            'bank_name' => 'nullable|string|max:255',
            'bik' => 'nullable|string|max:20',
            'checking_account' => 'nullable|string|max:50',
            'director_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',

            // Финансовые настройки юридического лица
            'nds_rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'decimal:0,2',
            ],
            'income_tax_rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'decimal:0,2',
            ],
        ], [
            'nds_rate.numeric' => 'Ставка НДС должна быть числом.',
            'nds_rate.min' => 'Ставка НДС не может быть отрицательной.',
            'nds_rate.max' => 'Ставка НДС не может превышать 100%.',
            'nds_rate.decimal' => 'Укажите ставку НДС с точностью до двух знаков после запятой.',

            'income_tax_rate.numeric' => 'Ставка налога с дохода должна быть числом.',
            'income_tax_rate.min' => 'Ставка налога с дохода не может быть отрицательной.',
            'income_tax_rate.max' => 'Ставка налога с дохода не может превышать 100%.',
            'income_tax_rate.decimal' => 'Укажите ставку налога с точностью до двух знаков после запятой.',
        ]);

        $item = LegalEntity::create($validated);
        return response()->json($item, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(LegalEntity::findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = LegalEntity::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'full_name' => 'nullable|string|max:500',
            'inn' => 'nullable|string|max:20',
            'kpp' => 'nullable|string|max:20',
            'ogrn' => 'nullable|string|max:30',
            'legal_address' => 'nullable|string|max:500',
            'actual_address' => 'nullable|string|max:500',
            'bank_name' => 'nullable|string|max:255',
            'bik' => 'nullable|string|max:20',
            'checking_account' => 'nullable|string|max:50',
            'director_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',

            // Финансовые настройки юридического лица
            'nds_rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'decimal:0,2',
            ],
            'income_tax_rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'decimal:0,2',
            ],
        ], [
            'nds_rate.numeric' => 'Ставка НДС должна быть числом.',
            'nds_rate.min' => 'Ставка НДС не может быть отрицательной.',
            'nds_rate.max' => 'Ставка НДС не может превышать 100%.',
            'nds_rate.decimal' => 'Укажите ставку НДС с точностью до двух знаков после запятой.',

            'income_tax_rate.numeric' => 'Ставка налога с дохода должна быть числом.',
            'income_tax_rate.min' => 'Ставка налога с дохода не может быть отрицательной.',
            'income_tax_rate.max' => 'Ставка налога с дохода не может превышать 100%.',
            'income_tax_rate.decimal' => 'Укажите ставку налога с точностью до двух знаков после запятой.',
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    public function destroy(int $id): JsonResponse
    {
        LegalEntity::findOrFail($id)->delete();
        return response()->json(['message' => 'Юридическое лицо удалено']);
    }
}