<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Список пользователей с пагинацией и поиском.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['group', 'city']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('group_id')) {
            $query->where('user_group_id', $request->group_id);
        }

        $users = $query->orderBy('name')->paginate($request->input('per_page', 20));

        return response()->json($users);
    }

    /**
     * Создание нового пользователя.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'user_group_id' => 'required|exists:user_groups,id',
            'city_id' => 'nullable|exists:cities,id',
            'password' => 'required|string|min:6|confirmed',
            'def_mrk_proc' => 'nullable|integer|min:0|max:100',
            'def_zap_proc' => 'nullable|integer|min:0|max:100',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json($user->load(['group', 'city']), 201);
    }

    /**
     * Просмотр пользователя.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['group', 'city'])->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Обновление данных пользователя.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:50',
            'user_group_id' => 'nullable|exists:user_groups,id',
            'city_id' => 'nullable|exists:cities,id',
            'password' => 'nullable|string|min:6|confirmed',
            'def_mrk_proc' => 'nullable|integer|min:0|max:100',
            'def_zap_proc' => 'nullable|integer|min:0|max:100',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->fresh(['group', 'city']));
    }

    /**
     * Удаление пользователя.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            return response()->json([
                'message' => 'Нельзя удалить собственный аккаунт'
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Пользователь успешно удален']);
    }
}