<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Nene2\Auth\TokenVerificationException;
use Nene2\Auth\TokenVerifierInterface;
use NeneClear\User\User;
use Throwable;

/**
 * Issues and verifies HS256 JWTs for operator authentication.
 *
 * Implements the framework {@see TokenVerifierInterface} so it plugs directly
 * into {@see \Nene2\Auth\BearerTokenMiddleware}. Claims: `sub` (user id),
 * `org` (organization id or null), `role`, `iat`, `exp`.
 */
final readonly class JwtTokenService implements TokenIssuerInterface, TokenVerifierInterface
{
    private const string ALGORITHM = 'HS256';

    public function __construct(
        private string $secret,
        private int $ttlSeconds = 3600,
    ) {
    }

    public function issueForUser(User $user): string
    {
        $now = time();

        return JWT::encode(
            [
                'sub' => $user->id,
                'org' => $user->organizationId,
                'role' => $user->role->value,
                'iat' => $now,
                'exp' => $now + $this->ttlSeconds,
            ],
            $this->secret,
            self::ALGORITHM,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TokenVerificationException
     */
    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable $e) {
            throw new TokenVerificationException($e->getMessage(), previous: $e);
        }

        return (array) $decoded;
    }
}
