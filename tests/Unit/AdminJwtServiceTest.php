<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AdminJwtService;
use Tests\TestCase;
use UnexpectedValueException;

class AdminJwtServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'admin.jwt_secret' => str_repeat('s', 64),
            'admin.jwt_ttl_minutes' => 10,
            'admin.jwt_issuer' => 'mp-auth-test',
            'admin.jwt_audience' => 'mp-front-test',
        ]);
    }

    public function test_it_issues_and_verifies_a_signed_token(): void
    {
        $user = new User(['name' => 'Admin', 'email' => 'admin@example.com']);
        $user->id = 42;

        $service = app(AdminJwtService::class);
        $issued = $service->issue($user);
        $payload = $service->verify($issued['token']);

        $this->assertSame('42', $payload['sub']);
        $this->assertSame('admin@example.com', $payload['email']);
        $this->assertSame(600, $issued['expires_in']);
    }

    public function test_it_rejects_a_tampered_token(): void
    {
        $user = new User(['name' => 'Admin', 'email' => 'admin@example.com']);
        $user->id = 42;

        $service = app(AdminJwtService::class);
        $token = $service->issue($user)['token'];
        $token[strlen($token) - 1] = $token[strlen($token) - 1] === 'a' ? 'b' : 'a';

        $this->expectException(UnexpectedValueException::class);
        $service->verify($token);
    }
}
