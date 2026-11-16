<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsuranceCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsuranceCompanyController extends Controller
{
    public function index(): JsonResponse
    {
        $companies = InsuranceCompany::with(['ourCompany', 'legalEntity'])
            ->withCount('applications')
            ->orderBy('name')
            ->get();

        return response()->json($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'manager' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'our_company_id' => 'nullable|exists:our_companies,id',
            'legal_entity_id' => 'nullable|exists:legal_entities,id',
            'theme' => 'nullable|string',
        ]);

        $company = InsuranceCompany::create($validated);

        return response()->json($company, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $company = InsuranceCompany::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email',
            'manager' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'our_company_id' => 'nullable|exists:our_companies,id',
            'legal_entity_id' => 'nullable|exists:legal_entities,id',
            'theme' => 'nullable|string',
        ]);

        $company->update($validated);

        return response()->json($company);
    }

    public function destroy(int $id): JsonResponse
    {
        $company = InsuranceCompany::withCount('applications')->findOrFail($id);

        if ($company->applications_count > 0) {
            return response()->json([
                'message' => 'Невозможно удалить страховую компанию, к которой привязаны заявки'
            ], 422);
        }

        $company->delete();

        return response()->json(['message' => 'Страховая компания удалена']);
    }
}