<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = UserGroup::withCount('users');

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                    ->orWhere('code', 'LIKE', "%{$s}%")
                    ->orWhere('description', 'LIKE', "%{$s}%");
            });
        }

        $groups = $query->orderBy('id', 'asc')->get();
        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:user_groups,code'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Введите наименование роли.',
            'code.required' => 'Введите системный код роли.',
            'code.unique' => 'Роль с таким системным кодом уже существует.',
            'code.alpha_dash' => 'Код роли может содержать только латинские буквы, цифры, дефис и подчеркивание.',
        ]);

        // Защита: роль sys_admin нельзя создать через веб-интерфейс
        if (strtolower($validated['code']) === 'sys_admin') {
            return response()->json([
                'message' => 'Системный администратор может быть создан только через консоль сервера.'
            ], 422);
        }

        $group = UserGroup::create($validated);

        return response()->json($group, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(UserGroup::withCount('users')->findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $group = UserGroup::findOrFail($id);

        // Защита: системную группу нельзя переименовать или изменить ее код
        if ($group->code === 'sys_admin') {
            return response()->json([
                'message' => 'Системную роль администратора нельзя изменять через веб-интерфейс.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('user_groups')->ignore($group->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Введите наименование роли.',
            'code.required' => 'Введите системный код роли.',
            'code.unique' => 'Роль с таким системным кодом уже существует.',
        ]);

        if (strtolower($validated['code']) === 'sys_admin') {
            return response()->json([
                'message' => 'Нельзя назначить зарезервированный код sys_admin.'
            ], 422);
        }

        $group->update($validated);

        return response()->json($group);
    }

    public function destroy(int $id): JsonResponse
    {
        $group = UserGroup::withCount('users')->findOrFail($id);

        if ($group->code === 'sys_admin') {
            return response()->json([
                'message' => 'Нельзя удалить системную роль администратора.'
            ], 403);
        }

        if ($group->users_count > 0) {
            return response()->json([
                'message' => "Нельзя удалить роль, так как к ней привязано пользователей: {$group->users_count}."
            ], 422);
        }

        $group->delete();

        return response()->json(['message' => 'Роль успешно удалена']);
    }
}