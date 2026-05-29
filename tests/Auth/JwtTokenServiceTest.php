<?php

declare(strict_types=1);

namespace NeneClear\Tests\Auth;

use Nene2\Auth\TokenVerificationException;
use NeneClear\Auth\JwtTokenService;
use NeneClear\Auth\Role;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class JwtTokenServiceTest extends TestCase
{
    private function user(): User
    {
        return new User(
            email: 'admin@acme.example',
            role: Role::Admin,
            status: UserStatus::Active,
            passwordHash: 'hash',
            organizationId: 7,
            id: 42,
        );
    }

    public function test_issue_then_verify_round_trips_claims(): void
    {
        $service = new JwtTokenService(secret: 'test-secret-test-secret-32chars!', ttlSeconds: 3600);

        $claims = $service->verify($service->issueForUser($this->user()));

        self::assertSame(42, $claims['sub'] ?? null);
        self::assertSame(7, $claims['org'] ?? null);
        self::assertSame('admin', $claims['role'] ?? null);
    }

    public function test_verify_rejects_expired_token(): void
    {
        $service = new JwtTokenService(secret: 'test-secret-test-secret-32chars!', ttlSeconds: -10);
        $expired = $service->issueForUser($this->user());

        $this->expectException(TokenVerificationException::class);
        $service->verify($expired);
    }

    public function test_verify_rejects_token_signed_with_other_secret(): void
    {
        $issuer = new JwtTokenService(secret: 'secret-one-secret-one-secret-one!', ttlSeconds: 3600);
        $verifier = new JwtTokenService(secret: 'secret-two-secret-two-secret-two!', ttlSeconds: 3600);

        $this->expectException(TokenVerificationException::class);
        $verifier->verify($issuer->issueForUser($this->user()));
    }
}
