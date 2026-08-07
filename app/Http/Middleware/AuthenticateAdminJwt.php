<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AdminAccess;
use App\Services\AdminJwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class AuthenticateAdminJwt
{
    public function __construct(
        private readonly AdminJwtService $jwt,
        private readonly AdminAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->unauthorized();
        }

        try {
            $payload = $this->jwt->verify($token);
            $user = User::query()->find((int) $payload['sub']);
        } catch (Throwable) {
            return $this->unauthorized();
        }

        if (! $user || ! hash_equals(strtolower($user->email), strtolower((string) ($payload['email'] ?? ''))) || ! $this->access->allows($user)) {
            return $this->unauthorized();
        }

        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private function unauthorized()
    {
        return response()->json([
            'success' => false,
            'message' => 'Требуется авторизация.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
