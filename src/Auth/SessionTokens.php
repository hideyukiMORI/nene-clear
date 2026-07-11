<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\User\User;

/**
 * Maps an authenticated {@see User} to Clear's session-token claims and signs
 * them through the framework {@see TokenIssuerInterface} (#285).
 *
 * Claims are the fleet shape: `sub` (user id), `org` (organization id or null
 * for a cross-tenant superadmin), `role`, `iat`, `exp`. Signing/encoding is
 * entirely the framework issuer's job — this class owns only the claim shape
 * and the TTL, so the product no longer touches JWT primitives directly.
 */
final readonly class SessionTokens
{
    public function __construct(
        private TokenIssuerInterface $issuer,
        private ClockInterface $clock,
        private int $ttlSeconds = 3600,
    ) {
    }

    public function issueForUser(User $user): string
    {
        $now = $this->clock->now()->getTimestamp();

        return $this->issuer->issue([
            'sub' => $user->id,
            'org' => $user->organizationId,
            'role' => $user->role->value,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ]);
    }
}
