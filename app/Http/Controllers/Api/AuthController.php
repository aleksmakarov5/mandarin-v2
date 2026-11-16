<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Авторизация пользователя и выдача Sanctum токена
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('group')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Предоставленные учетные данные не соответствуют нашим записям.'],
            ]);
        }

        // Создаем токен для SPA / мобильного клиента
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->group?->code,
                'role_name' => $user->group?->name,
            ],
        ]);
    }

    /**
     * Получение информации о текущем авторизованном пользователе
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('group');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->group?->code,
            'role_name' => $user->group?->name,
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