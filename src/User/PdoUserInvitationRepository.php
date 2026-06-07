<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoUserInvitationRepository implements UserInvitationRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, user_id, token_hash, expires_at, accepted_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function save(UserInvitation $invitation): int
    {
        $this->query->execute(
            'INSERT INTO user_invitations (organization_id, user_id, token_hash, expires_at, accepted_at) '
            . 'VALUES (?, ?, ?, ?, ?)',
            [
                $invitation->organizationId,
                $invitation->userId,
                $invitation->tokenHash,
                $invitation->expiresAt,
                $invitation->acceptedAt,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function findByTokenHash(string $tokenHash): ?UserInvitation
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM user_invitations WHERE token_hash = ?',
            [$tokenHash],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function markAccepted(int $id, string $acceptedAt): void
    {
        $this->query->execute(
            'UPDATE user_invitations SET accepted_at = ? WHERE id = ?',
            [$acceptedAt, $id],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): UserInvitation
    {
        return new UserInvitation(
            organizationId: $row['organization_id'] !== null ? (int) $row['organization_id'] : null,
            userId: (int) $row['user_id'],
            tokenHash: (string) $row['token_hash'],
            expiresAt: (string) $row['expires_at'],
            acceptedAt: $row['accepted_at'] !== null ? (string) $row['accepted_at'] : null,
            id: (int) $row['id'],
        );
    }
}
