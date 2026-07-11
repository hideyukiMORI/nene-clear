<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Auth\TokenVerificationException;
use Nene2\Http\ClockInterface;
use Nene2\Http\UtcClock;

/**
 * Issues and verifies the short-lived token handed out when a password is
 * correct but the user still owes a TOTP code. It is signed with a secret
 * *derived* from (but distinct from) the session-token secret, so a challenge
 * token can never be presented as a session bearer token and vice versa.
 *
 * Built on the framework {@see LocalBearerTokenVerifier} (HS256) since #285 —
 * the wire format is unchanged, so challenge tokens issued before the
 * migration keep verifying.
 */
final readonly class MfaChallengeTokens
{
    private LocalBearerTokenVerifier $tokens;
    private ClockInterface $clock;

    public function __construct(string $sessionSecret, private int $ttlSeconds = 300, ?ClockInterface $clock = null)
    {
        $this->clock = $clock ?? new UtcClock();
        $this->tokens = new LocalBearerTokenVerifier(
            hash_hmac('sha256', 'nene-clear:mfa-challenge:v1', $sessionSecret),
            $this->clock,
        );
    }

    public function issue(int $userId): string
    {
        $now = $this->clock->now()->getTimestamp();

        return $this->tokens->issue(
            ['sub' => $userId, 'mfa' => 'pending', 'iat' => $now, 'exp' => $now + $this->ttlSeconds],
        );
    }

    /** @throws MfaChallengeInvalidException when the token is missing, malformed, expired, or not an MFA challenge */
    public function verifyUserId(string $token): int
    {
        try {
            $decoded = $this->tokens->verify($token);
        } catch (TokenVerificationException) {
            throw new MfaChallengeInvalidException();
        }

        if (($decoded['mfa'] ?? null) !== 'pending') {
            throw new MfaChallengeInvalidException();
        }

        $sub = $decoded['sub'] ?? null;
        $userId = is_int($sub) ? $sub : (is_numeric($sub) ? (int) $sub : 0);
        if ($userId <= 0) {
            throw new MfaChallengeInvalidException();
        }

        return $userId;
    }
}
