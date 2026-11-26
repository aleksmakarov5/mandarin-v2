<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Авторизация пользователя и выдача Sanctum Bearer токена
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email:rfc,filter'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Введите адрес электронной почты.',
            'email.email' => 'Укажите корректный адрес электронной почты.',
            'password.required' => 'Введите пароль.',
        ]);

        $user = User::with('group')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Предоставленные учетные данные не соответствуют нашим записям.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'patronymic' => $user->patronymic,
                'full_name' => $user->full_name,
                'short_name' => $user->short_name,
                'email' => $user->email,
                'phone' => $user->phone,

                'user_group_id' => $user->user_group_id !== null
                    ? (int) $user->user_group_id
                    : null,

                'role' => $user->group?->code,
                'role_name' => $user->group?->name,

                'is_system_admin' => (bool) $user->isSystemAdmin(),

                'menu_permissions' => $user->menuPermissions(),
                'is_sto' => method_exists($user, 'isSto')
                    ? (bool) $user->isSto()
                    : (int) ($user->user_group_id ?? $user->GroupId ?? 0) === 8,

                'is_spm' => method_exists($user, 'isSpm')
                    ? (bool) $user->isSpm()
                    : (int) ($user->user_group_id ?? $user->GroupId ?? 0) === 9,
            ],
        ]);
    }

    /**
     * Получение данных текущего авторизованного пользователя
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('group');

        return response()
            ->json([
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'patronymic' => $user->patronymic,
                'full_name' => $user->full_name,
                'short_name' => $user->short_name,
                'email' => $user->email,
                'phone' => $user->phone,

                // Основная группа пользователя.
                'user_group_id' => $user->user_group_id !== null
                    ? (int) $user->user_group_id
                    : null,

                'role' => $user->group?->code,
                'role_name' => $user->group?->name,

                // Авторитетная проверка из модели User.
                'is_system_admin' => (bool) $user->isSystemAdmin(),

                // Готовые разрешения меню от backend.
                'menu_permissions' => $user->menuPermissions(),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Обновление личного профиля текущего пользователя
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'patronymic' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'required',
                'string',
                'email:rfc,filter',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password' => ['nullable', 'confirmed', Password::defaults()],
        ], [
            'last_name.required' => 'Фамилия обязательна для заполнения.',
            'name.required' => 'Имя обязательно для заполнения.',
            'email.required' => 'Email обязателен для заполнения.',
            'email.email' => 'Укажите корректный адрес электронной почты.',
            'email.unique' => 'Этот Email уже занят другим пользователем.',
            'current_password.required_with' => 'Введите текущий пароль для подтверждения смены пароля.',
            'new_password.confirmed' => 'Подтверждение нового пароля не совпадает.',
            'new_password.min' => 'Новый пароль должен содержать не менее :min символов.',
        ]);

        if (!empty($validated['new_password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'Текущий пароль указан неверно.',
                    'errors' => [
                        'current_password' => ['Текущий пароль указан неверно.'],
                    ],
                ], 422);
            }

            $user->password = Hash::make($validated['new_password']);
        }

        $user->last_name = trim($validated['last_name']);
        $user->name = trim($validated['name']);
        $user->patronymic = !empty($validated['patronymic']) ? trim($validated['patronymic']) : null;
        $user->phone = !empty($validated['phone']) ? trim($validated['phone']) : null;
        $user->email = trim($validated['email']);
        $user->save();

        $user->load('group');

        return response()->json([
            'message' => 'Профиль успешно обновлен',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'patronymic' => $user->patronymic,
                'full_name' => $user->full_name,
                'short_name' => $user->short_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->group?->code,
                'role_name' => $user->group?->name,
            ],
        ]);
    }

    /**
     * Выход из системы (отзыв текущего токена)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Сессия успешно завершена',
        ]);
    }
}