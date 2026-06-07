<?php

declare(strict_types=1);

namespace NeneClear\User;

/**
 * An operator onboarding token. The raw token is e-mailed to the invitee and
 * never stored; only its SHA-256 hash lives here (terminology.md §3). An
 * invitation is usable while `acceptedAt` is null and `expiresAt` is in the
 * future; accepting it sets the user's password and flips the account to active.
 */
final readonly class UserInvitation
{
    public function __construct(
        public ?int $organizationId,
        public int $userId,
        public string $tokenHash,
        public string $expiresAt,
        public ?string $acceptedAt = null,
        public ?int $id = null,
    ) {
    }
}
