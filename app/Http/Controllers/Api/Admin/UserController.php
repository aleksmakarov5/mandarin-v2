<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with([
            'group',
            'cities',
            'branchRoles.branch',
            'branchRoles.role',
        ]);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('patronymic', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('branchRoles', fn($q) => $q->where('our_company_id', $request->branch_id));
        }

        if ($request->filled('city_id')) {
            $query->whereHas('cities', fn($q) => $q->where('cities.id', $request->city_id));
        }

        $perPage = (int) $request->input('per_page', 20);
        $users = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($users);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'group',
            'cities',
            'branchRoles.branch',
            'branchRoles.role',
        ])->findOrFail($id);

        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'patronymic' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,filter', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'user_group_id' => ['nullable', 'exists:user_groups,id'], // Базовая роль (если используется)
            'password' => ['required', 'confirmed', Password::defaults()],
            'def_mrk_proc' => ['nullable', 'integer', 'min:0', 'max:100'],
            'def_zap_proc' => ['nullable', 'integer', 'min:0', 'max:100'],

            // Привязка к городам
            'city_ids' => ['nullable', 'array'],
            'city_ids.*' => ['integer', 'exists:cities,id'],

            // Привязка: Филиал + Роль
            'branch_roles' => ['nullable', 'array'],
            'branch_roles.*.our_company_id' => ['required', 'integer', 'exists:our_companies,id'],
            'branch_roles.*.user_group_id' => ['required', 'integer', 'exists:user_groups,id'],
        ], [
            'last_name.required' => 'Укажите фамилию.',
            'name.required' => 'Укажите имя.',
            'email.required' => 'Укажите email.',
            'email.unique' => 'Пользователь с таким email уже существует.',
            'password.required' => 'Укажите пароль.',
            'password.confirmed' => 'Подтверждение пароля не совпадает.',
        ]);

        // Защита: роль sys_admin нельзя назначить через веб-интерфейс
        $sysAdminGroup = UserGroup::where('code', 'sys_admin')->first();
        if ($sysAdminGroup) {
            if (!empty($validated['branch_roles'])) {
                foreach ($validated['branch_roles'] as $br) {
                    if ((int) $br['user_group_id'] === $sysAdminGroup->id) {
                        return response()->json(['message' => 'Роль системного администратора нельзя назначить через интерфейс.'], 422);
                    }
                }
            }
            if (!empty($validated['user_group_id']) && (int) $validated['user_group_id'] === $sysAdminGroup->id) {
                return response()->json(['message' => 'Роль системного администратора нельзя назначить через интерфейс.'], 422);
            }
        }

        $user = DB::transaction(function () use ($validated) {
            $userData = [
                'last_name' => trim($validated['last_name']),
                'name' => trim($validated['name']),
                'patronymic' => !empty($validated['patronymic']) ? trim($validated['patronymic']) : null,
                'email' => trim($validated['email']),
                'phone' => !empty($validated['phone']) ? trim($validated['phone']) : null,
                'user_group_id' => $validated['user_group_id'] ?? null,
                'def_mrk_proc' => $validated['def_mrk_proc'] ?? null,
                'def_zap_proc' => $validated['def_zap_proc'] ?? null,
                'password' => Hash::make($validated['password']),
                'is_system_admin' => false,
            ];

            $user = User::create($userData);

            // Синхронизация городов
            if (isset($validated['city_ids'])) {
                $user->cities()->sync($validated['city_ids']);
            }

            // Назначение филиалов и ролей
            if (!empty($validated['branch_roles'])) {
                foreach ($validated['branch_roles'] as $item) {
                    $user->branchRoles()->create([
                        'our_company_id' => $item['our_company_id'],
                        'user_group_id' => $item['user_group_id'],
                        'is_active' => true,
                    ]);
                }
            }

            return $user;
        });

        return response()->json($user->load(['group', 'cities', 'branchRoles.branch', 'branchRoles.role']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'patronymic' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc,filter', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'user_group_id' => ['nullable', 'exists:user_groups,id'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'def_mrk_proc' => ['nullable', 'integer', 'min:0', 'max:100'],
            'def_zap_proc' => ['nullable', 'integer', 'min:0', 'max:100'],

            // Привязка к городам
            'city_ids' => ['nullable', 'array'],
            'city_ids.*' => ['integer', 'exists:cities,id'],

            // Привязка: Филиал + Роль
            'branch_roles' => ['nullable', 'array'],
            'branch_roles.*.our_company_id' => ['required', 'integer', 'exists:our_companies,id'],
            'branch_roles.*.user_group_id' => ['required', 'integer', 'exists:user_groups,id'],
        ], [
            'last_name.required' => 'Укажите фамилию.',
            'name.required' => 'Укажите имя.',
            'email.required' => 'Укажите email.',
            'email.unique' => 'Пользователь с таким email уже существует.',
            'password.confirmed' => 'Подтверждение пароля не совпадает.',
        ]);

        // Защита: роль sys_admin нельзя назначить через веб-интерфейс
        $sysAdminGroup = UserGroup::where('code', 'sys_admin')->first();
        if ($sysAdminGroup && !empty($validated['branch_roles'])) {
            foreach ($validated['branch_roles'] as $br) {
                if ((int) $br['user_group_id'] === $sysAdminGroup->id) {
                    return response()->json(['message' => 'Роль системного администратора нельзя назначить через интерфейс.'], 422);
                }
            }
        }

        DB::transaction(function () use ($user, $validated) {
            $userData = [];

            if (isset($validated['last_name']))
                $userData['last_name'] = trim($validated['last_name']);
            if (isset($validated['name']))
                $userData['name'] = trim($validated['name']);
            if (array_key_exists('patronymic', $validated))
                $userData['patronymic'] = !empty($validated['patronymic']) ? trim($validated['patronymic']) : null;
            if (isset($validated['email']))
                $userData['email'] = trim($validated['email']);
            if (array_key_exists('phone', $validated))
                $userData['phone'] = !empty($validated['phone']) ? trim($validated['phone']) : null;
            if (array_key_exists('user_group_id', $validated))
                $userData['user_group_id'] = $validated['user_group_id'];
            if (array_key_exists('def_mrk_proc', $validated))
                $userData['def_mrk_proc'] = $validated['def_mrk_proc'];
            if (array_key_exists('def_zap_proc', $validated))
                $userData['def_zap_proc'] = $validated['def_zap_proc'];

            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            // Синхронизация городов
            if (array_key_exists('city_ids', $validated)) {
                $user->cities()->sync($validated['city_ids'] ?? []);
            }

            // Синхронизация филиалов и ролей
            if (array_key_exists('branch_roles', $validated)) {
                $user->branchRoles()->delete();
                if (!empty($validated['branch_roles'])) {
                    foreach ($validated['branch_roles'] as $item) {
                        $user->branchRoles()->create([
                            'our_company_id' => $item['our_company_id'],
                            'user_group_id' => $item['user_group_id'],
                            'is_active' => true,
                        ]);
                    }
                }
            }
        });

        return response()->json($user->fresh(['group', 'cities', 'branchRoles.branch', 'branchRoles.role']));
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'Нельзя удалить собственный аккаунт.'], 422);
        }

        $user->cities()->detach();
        $user->branchRoles()->delete();
        $user->delete();

        return response()->json(['message' => 'Пользователь успешно удален']);
    }
}