<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Service::with([
            'user:id,name,last_name,email,phone',
            'branches:id,name', // Заменено our_name -> name
        ]);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                    ->orWhere('phone', 'LIKE', "%{$s}%")
                    ->orWhere('email', 'LIKE', "%{$s}%")
                    ->orWhere('address', 'LIKE', "%{$s}%");
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('branches', fn($q) => $q->where('our_companies.id', $request->branch_id));
        }

        $items = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function getServiceUsers(): JsonResponse
    {
        $users = User::where(function ($q) {
            $q->where('user_group_id', 7)
                ->orWhereHas('group', fn($g) => $g->whereIn('code', ['service', 'sto', 'service_partner']))
                ->orWhereHas('branchRoles.role', fn($g) => $g->whereIn('code', ['service', 'sto', 'service_partner']));
        })
            ->orderBy('last_name')
            ->orderBy('name')
            ->get(['id', 'name', 'last_name', 'email', 'phone']);

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string'],
            'bg_color' => ['nullable', 'string', 'max:30'],
            'text_color' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:our_companies,id'],
        ], [
            'name.required' => 'Укажите название автосервиса.',
        ]);

        $service = DB::transaction(function () use ($validated) {
            $service = Service::create([
                'name' => trim($validated['name']),
                'user_id' => $validated['user_id'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'bg_color' => $validated['bg_color'] ?? '#e0f2fe',
                'text_color' => $validated['text_color'] ?? '#0369a1',
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (!empty($validated['branch_ids'])) {
                $service->branches()->sync($validated['branch_ids']);
            }

            return $service;
        });

        return response()->json([
            'message' => 'Автосервис успешно добавлен',
            'data' => $service->load(['user:id,name,last_name,email,phone', 'branches:id,name']),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $service = Service::with([
            'user:id,name,last_name,email,phone',
            'branches:id,name', // Заменено our_name -> name
        ])->findOrFail($id);

        return response()->json([
            'data' => $service,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string'],
            'bg_color' => ['nullable', 'string', 'max:30'],
            'text_color' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:our_companies,id'],
        ], [
            'name.required' => 'Укажите название автосервиса.',
        ]);

        DB::transaction(function () use ($service, $validated) {
            $service->update([
                'name' => trim($validated['name']),
                'user_id' => $validated['user_id'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'bg_color' => $validated['bg_color'] ?? '#e0f2fe',
                'text_color' => $validated['text_color'] ?? '#0369a1',
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (array_key_exists('branch_ids', $validated)) {
                $service->branches()->sync($validated['branch_ids'] ?? []);
            }
        });

        return response()->json([
            'message' => 'Автосервис успешно обновлен',
            'data' => $service->fresh(['user:id,name,last_name,email,phone', 'branches:id,name']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $service = Service::findOrFail($id);
        $service->branches()->detach();
        $service->delete();

        return response()->json([
            'message' => 'Автосервис удален',
        ]);
    }
}