<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = UserGroup::withCount('users')->orderBy('id')->get();
        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:user_groups,code',
            'description' => 'nullable|string',
        ]);

        $group = UserGroup::create($validated);

        return response()->json($group, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $group = UserGroup::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => "nullable|string|max:50|unique:user_groups,code,{$id}",
            'description' => 'nullable|string',
        ]);

        $group->update($validated);

        return response()->json($group);
    }

    public function destroy(int $id): JsonResponse
    {
        $group = UserGroup::withCount('users')->findOrFail($id);

        if ($group->users_count > 0) {
            return response()->json([
                'message' => 'Невозможно удалить группу, в которой состоят пользователи'
            ], 422);
        }

        $group->delete();

        return response()->json(['message' => 'Группа пользователей удалена']);
    }
}