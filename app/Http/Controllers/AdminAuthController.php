<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminAccess;
use App\Services\AdminJwtService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AdminAuthController extends Controller
{
    public function login(Request $request, AdminAccess $access, AdminJwtService $jwt)
    {
        $credentials = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|max:1024',
        ]);

        $user = User::query()->where('email', strtolower($credentials['email']))->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $access->allows($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный email или пароль.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $issued = $jwt->issue($user);
        } catch (RuntimeException) {
            Log::critical('Admin JWT configuration is invalid.');

            return response()->json([
                'success' => false,
                'message' => 'Сервис авторизации временно недоступен.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json([
            'success' => true,
            'token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_in' => $issued['expires_in'],
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
