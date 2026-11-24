<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsuranceCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class InsuranceCompanyController extends Controller
{
    public function index(): JsonResponse
    {
        $companies = InsuranceCompany::query()
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $companies,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bg_color' => 'nullable|string|max:50',
            'text_color' => 'nullable|string|max:50',
            'curator' => 'nullable|string|max:255',
            'manager' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'split_ic_total' => ['sometimes', 'boolean'],
        ]);

        $columns = Schema::getColumnListing('insurance_companies');
        $data = [];

        // Базовые поля
        if (in_array('name', $columns, true))
            $data['name'] = $validated['name'];
        if (in_array('bg_color', $columns, true))
            $data['bg_color'] = $validated['bg_color'] ?? '#fce7f3';
        if (in_array('text_color', $columns, true))
            $data['text_color'] = $validated['text_color'] ?? '#be185d';
        if (in_array('phone', $columns, true))
            $data['phone'] = $validated['phone'] ?? null;
        if (in_array('email', $columns, true))
            $data['email'] = $validated['email'] ?? null;
        if (in_array('address', $columns, true))
            $data['address'] = $validated['address'] ?? null;
        // Настройка раздельной согласованной суммы.
// Если поле не передано, сохраняется прежнее поведение создания.
        if (
            array_key_exists('split_ic_total', $validated)
            && in_array('split_ic_total', $columns, true)
        ) {
            $data['split_ic_total'] = $validated['split_ic_total'];
        }

        // Куратор / Менеджер (поддержка обоих вариантов)
        $curatorValue = $validated['curator'] ?? $validated['manager'] ?? null;
        if (in_array('curator', $columns, true)) {
            $data['curator'] = $curatorValue;
        } elseif (in_array('manager', $columns, true)) {
            $data['manager'] = $curatorValue;
        }

        $company = InsuranceCompany::create($data);

        return response()->json([
            'message' => 'Страховая компания создана',
            'data' => $company,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $company = InsuranceCompany::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'bg_color' => 'nullable|string|max:50',
            'text_color' => 'nullable|string|max:50',
            'curator' => 'nullable|string|max:255',
            'manager' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'split_ic_total' => ['sometimes', 'boolean'],
        ]);

        $columns = Schema::getColumnListing('insurance_companies');
        $data = [];

        foreach ([
            'name',
            'bg_color',
            'text_color',
            'phone',
            'email',
            'address',
            'split_ic_total',
        ] as $col) {
            if (array_key_exists($col, $validated) && in_array($col, $columns, true)) {
                $data[$col] = $validated[$col];
            }
        }

        // Поддержка куратора/менеджера
        if (array_key_exists('curator', $validated) || array_key_exists('manager', $validated)) {
            $curatorValue = $validated['curator'] ?? $validated['manager'] ?? null;
            if (in_array('curator', $columns, true)) {
                $data['curator'] = $curatorValue;
            } elseif (in_array('manager', $columns, true)) {
                $data['manager'] = $curatorValue;
            }
        }

        $company->update($data);

        return response()->json([
            'message' => 'Страховая компания обновлена',
            'data' => $company,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $company = InsuranceCompany::findOrFail($id);
        $company->delete();

        return response()->json([
            'message' => 'Страховая компания удалена',
        ]);
    }
}