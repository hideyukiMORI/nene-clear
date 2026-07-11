<?php

declare(strict_types=1);

namespace NeneClear\Tests\Auth;

use Nene2\Auth\LocalBearerTokenVerifier;
use NeneClear\Auth\MfaChallengeInvalidException;
use NeneClear\Auth\MfaChallengeTokens;
use NeneClear\Auth\Role;
use NeneClear\Auth\SessionTokens;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

/**
 * Pins the MFA challenge-token behaviour after the #285 migration onto the
 * framework {@see \Nene2\Auth\LocalBearerTokenVerifier}: same 300 s TTL, same
 * derived-secret domain separation from session tokens.
 */
final class MfaChallengeTokensTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';

    public function test_issue_then_verify_round_trips_the_user_id(): void
    {
        $tokens = new MfaChallengeTokens(self::SECRET, clock: new FixedClock('2026-06-01T10:00:00+00:00'));

        self::assertSame(42, $tokens->verifyUserId($tokens->issue(42)));
    }

    public function test_expired_challenge_is_rejected(): void
    {
        $issuer = new MfaChallengeTokens(self::SECRET, clock: new FixedClock('2026-06-01T10:00:00+00:00'));
        $token = $issuer->issue(42);

        // 300 s TTL: one second past the boundary must fail.
        $verifier = new MfaChallengeTokens(self::SECRET, clock: new FixedClock('2026-06-01T10:05:01+00:00'));
        $this->expectException(MfaChallengeInvalidException::class);
        $verifier->verifyUserId($token);
    }

    public function test_a_session_token_is_not_accepted_as_a_challenge(): void
    {
        // Same signing root, but the challenge secret is domain-separated —
        // a full session bearer token must never pass as an MFA challenge.
        $clock = new FixedClock('2026-06-01T10:00:00+00:00');
        $session = new SessionTokens(new LocalBearerTokenVerifier(self::SECRET, $clock), $clock);
        $sessionToken = $session->issueForUser(new User(
            email: 'admin@acme.example',
            role: Role::Admin,
            status: UserStatus::Active,
            passwordHash: 'hash',
            organizationId: 7,
            id: 42,
        ));

        $challenges = new MfaChallengeTokens(self::SECRET, clock: $clock);
        $this->expectException(MfaChallengeInvalidException::class);
        $challenges->verifyUserId($sessionToken);
    }

    public function test_a_challenge_is_not_accepted_as_a_session_token(): void
    {
        $clock = new FixedClock('2026-06-01T10:00:00+00:00');
        $challenge = (new MfaChallengeTokens(self::SECRET, clock: $clock))->issue(42);

        $sessionVerifier = new LocalBearerTokenVerifier(self::SECRET, $clock);
        $this->expectException(\Nene2\Auth\TokenVerificationException::class);
        $sessionVerifier->verify($challenge);
    }

    public function test_garbage_token_is_rejected(): void
    {
        $tokens = new MfaChallengeTokens(self::SECRET, clock: new FixedClock('2026-06-01T10:00:00+00:00'));

        $this->expectException(MfaChallengeInvalidException::class);
        $tokens->verifyUserId('not-a-jwt');
    }
}
