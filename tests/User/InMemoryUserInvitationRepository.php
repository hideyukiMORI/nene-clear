<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\User\UserInvitation;
use NeneClear\User\UserInvitationRepositoryInterface;

final class InMemoryUserInvitationRepository implements UserInvitationRepositoryInterface
{
    /** @var array<int, UserInvitation> */
    private array $byId = [];

    private int $nextId = 1;

    public function save(UserInvitation $invitation): int
    {
        $id = $invitation->id ?? $this->nextId++;
        $this->byId[$id] = new UserInvitation(
            organizationId: $invitation->organizationId,
            userId: $invitation->userId,
            tokenHash: $invitation->tokenHash,
            expiresAt: $invitation->expiresAt,
            acceptedAt: $invitation->acceptedAt,
            id: $id,
        );

        return $id;
    }

    public function findByTokenHash(string $tokenHash): ?UserInvitation
    {
        foreach ($this->byId as $invitation) {
            if (hash_equals($invitation->tokenHash, $tokenHash)) {
                return $invitation;
            }
        }

        return null;
    }

    public function markAccepted(int $id, string $acceptedAt): void
    {
        $existing = $this->byId[$id] ?? null;
        if ($existing === null) {
            return;
        }

        $this->byId[$id] = new UserInvitation(
            organizationId: $existing->organizationId,
            userId: $existing->userId,
            tokenHash: $existing->tokenHash,
            expiresAt: $existing->expiresAt,
            acceptedAt: $acceptedAt,
            id: $id,
        );
    }
}
