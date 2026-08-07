<?php

namespace App\Services;

use App\Models\User;
use JsonException;
use RuntimeException;
use UnexpectedValueException;

final class AdminJwtService
{
    /** @return array{token: string, expires_in: int} */
    public function issue(User $user): array
    {
        $now = time();
        $ttl = max(300, (int) config('admin.jwt_ttl_minutes', 480) * 60);
        $payload = [
            'iss' => (string) config('admin.jwt_issuer'),
            'aud' => (string) config('admin.jwt_audience'),
            'sub' => (string) $user->getKey(),
            'email' => $user->email,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $headerPart = $this->encodeJson(['alg' => 'HS256', 'typ' => 'JWT']);
        $payloadPart = $this->encodeJson($payload);
        $signature = $this->sign($headerPart.'.'.$payloadPart);

        return [
            'token' => $headerPart.'.'.$payloadPart.'.'.$signature,
            'expires_in' => $ttl,
        ];
    }

    /** @return array<string, mixed> */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new UnexpectedValueException('Malformed JWT.');
        }

        [$headerPart, $payloadPart, $signature] = $parts;
        $header = $this->decodeJson($headerPart);
        $payload = $this->decodeJson($payloadPart);

        if (($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'JWT') {
            throw new UnexpectedValueException('Unsupported JWT header.');
        }

        if (! hash_equals($this->sign($headerPart.'.'.$payloadPart), $signature)) {
            throw new UnexpectedValueException('Invalid JWT signature.');
        }

        $now = time();
        if (! is_int($payload['exp'] ?? null) || $payload['exp'] <= $now) {
            throw new UnexpectedValueException('Expired JWT.');
        }
        if (! is_int($payload['nbf'] ?? null) || $payload['nbf'] > $now + 30) {
            throw new UnexpectedValueException('JWT is not active.');
        }
        if (($payload['iss'] ?? null) !== config('admin.jwt_issuer')) {
            throw new UnexpectedValueException('Invalid JWT issuer.');
        }
        if (($payload['aud'] ?? null) !== config('admin.jwt_audience')) {
            throw new UnexpectedValueException('Invalid JWT audience.');
        }
        if (! is_string($payload['sub'] ?? null) || ! ctype_digit($payload['sub'])) {
            throw new UnexpectedValueException('Invalid JWT subject.');
        }

        return $payload;
    }

    private function secret(): string
    {
        $secret = (string) config('admin.jwt_secret', '');
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must contain at least 32 characters.');
        }

        return $secret;
    }

    private function sign(string $value): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $value, $this->secret(), true));
    }

    /** @param array<string, mixed> $value */
    private function encodeJson(array $value): string
    {
        try {
            return $this->base64UrlEncode(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to encode JWT.', previous: $e);
        }
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $value): array
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new UnexpectedValueException('Invalid JWT encoding.');
        }

        try {
            $json = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException('Invalid JWT JSON.', previous: $e);
        }

        if (! is_array($json)) {
            throw new UnexpectedValueException('Invalid JWT payload.');
        }

        return $json;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
