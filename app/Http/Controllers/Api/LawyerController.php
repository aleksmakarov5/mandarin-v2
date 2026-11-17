<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lawyer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LawyerController extends Controller
{
    public function index(): JsonResponse
    {
        $lawyers = Lawyer::select('id', 'name', 'phone', 'email', 'is_cooperating', 'theme')
            ->orderBy('name')
            ->get();

        return response()->json($lawyers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'is_cooperating' => 'nullable|boolean',
            'theme' => 'nullable|string|max:500',
            'comment' => 'nullable|string|max:500',
        ]);

        $lawyer = Lawyer::create([
            'name' => trim($validated['name']),
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_cooperating' => $request->has('is_cooperating') ? (bool) $request->input('is_cooperating') : true,
            'theme' => $validated['theme'] ?? $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Юрист успешно добавлен',
            'data' => $lawyer,
        ], 201);
    }
}