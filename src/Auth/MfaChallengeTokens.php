<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Issues and verifies the short-lived token handed out when a password is
 * correct but the user still owes a TOTP code. It is signed with a secret
 * *derived* from (but distinct from) the session-token secret, so a challenge
 * token can never be presented as a session bearer token and vice versa.
 */
final readonly class MfaChallengeTokens
{
    private const string ALGORITHM = 'HS256';

    private string $secret;

    public function __construct(string $sessionSecret, private int $ttlSeconds = 300)
    {
        $this->secret = hash_hmac('sha256', 'nene-clear:mfa-challenge:v1', $sessionSecret);
    }

    public function issue(int $userId): string
    {
        $now = time();

        return JWT::encode(
            ['sub' => $userId, 'mfa' => 'pending', 'iat' => $now, 'exp' => $now + $this->ttlSeconds],
            $this->secret,
            self::ALGORITHM,
        );
    }

    /** @throws MfaChallengeInvalidException when the token is missing, malformed, expired, or not an MFA challenge */
    public function verifyUserId(string $token): int
    {
        try {
            $decoded = (array) JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable $e) {
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
