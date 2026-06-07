<?php

declare(strict_types=1);

namespace NeneClear\User;

interface UserInvitationRepositoryInterface
{
    public function save(UserInvitation $invitation): int;

    /** Look up an invitation by its token hash (regardless of state). */
    public function findByTokenHash(string $tokenHash): ?UserInvitation;

    /** Mark an invitation consumed at the given timestamp. */
    public function markAccepted(int $id, string $acceptedAt): void;
}
