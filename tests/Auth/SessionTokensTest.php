<?php

declare(strict_types=1);

namespace NeneClear\Tests\Auth;

use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Auth\TokenVerificationException;
use NeneClear\Auth\Role;
use NeneClear\Auth\SessionTokens;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

/**
 * Pins the session-token behaviour on the fleet-standard NENE2 JWT stack
 * (#285): {@see SessionTokens} owns the claim shape and TTL, and the framework
 * {@see LocalBearerTokenVerifier} both signs and verifies.
 */
final class SessionTokensTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';

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

    private function tokens(string $issuedAt = '2026-06-01T10:00:00+00:00', int $ttl = 3600): SessionTokens
    {
        return new SessionTokens(
            new LocalBearerTokenVerifier(self::SECRET, new FixedClock($issuedAt)),
            new FixedClock($issuedAt),
            $ttl,
        );
    }

    public function test_issue_then_verify_round_trips_claims(): void
    {
        $verifier = new LocalBearerTokenVerifier(self::SECRET, new FixedClock('2026-06-01T10:00:00+00:00'));

        $claims = $verifier->verify($this->tokens()->issueForUser($this->user()));

        self::assertSame(42, $claims['sub'] ?? null);
        self::assertSame(7, $claims['org'] ?? null);
        self::assertSame('admin', $claims['role'] ?? null);
    }

    public function test_superadmin_org_claim_stays_null(): void
    {
        $superadmin = new User(
            email: 'root@clear.example',
            role: Role::Superadmin,
            status: UserStatus::Active,
            passwordHash: 'hash',
            organizationId: null,
            id: 1,
        );
        $verifier = new LocalBearerTokenVerifier(self::SECRET, new FixedClock('2026-06-01T10:00:00+00:00'));

        $claims = $verifier->verify($this->tokens()->issueForUser($superadmin));

        self::assertArrayHasKey('org', $claims);
        self::assertNull($claims['org']);
    }

    public function test_token_carries_iat_and_expires_after_ttl(): void
    {
        $token = $this->tokens(ttl: 3600)->issueForUser($this->user());

        // Valid one second before the TTL boundary…
        $justBefore = new LocalBearerTokenVerifier(self::SECRET, new FixedClock('2026-06-01T10:59:59+00:00'));
        $claims = $justBefore->verify($token);
        self::assertSame((new \DateTimeImmutable('2026-06-01T10:00:00+00:00'))->getTimestamp(), $claims['iat'] ?? null);

        // …rejected once the clock passes exp.
        $after = new LocalBearerTokenVerifier(self::SECRET, new FixedClock('2026-06-01T11:00:01+00:00'));
        $this->expectException(TokenVerificationException::class);
        $after->verify($token);
    }

    public function test_verify_rejects_token_signed_with_other_secret(): void
    {
        $token = $this->tokens()->issueForUser($this->user());
        $otherSecret = new LocalBearerTokenVerifier('secret-two-secret-two-secret-two!', new FixedClock('2026-06-01T10:00:00+00:00'));

        $this->expectException(TokenVerificationException::class);
        $otherSecret->verify($token);
    }
}
