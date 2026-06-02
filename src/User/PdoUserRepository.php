<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneClear\Auth\Role;

final readonly class PdoUserRepository implements UserRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, email, role, status, password_hash';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $id): ?User
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE id = ? AND is_deleted = 0',
            [$id],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE email = ? AND is_deleted = 0',
            [$email],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function existsByEmail(string $email): bool
    {
        return $this->query->fetchOne(
            'SELECT 1 FROM users WHERE email = ? AND is_deleted = 0',
            [$email],
        ) !== null;
    }

    public function findAllByOrganization(?int $organizationId, int $limit, int $offset): array
    {
        if ($organizationId === null) {
            $rows = $this->query->fetchAll(
                'SELECT ' . self::COLUMNS . ' FROM users WHERE organization_id IS NULL AND is_deleted = 0 '
                . 'ORDER BY id ASC LIMIT ? OFFSET ?',
                [$limit, $offset],
            );
        } else {
            $rows = $this->query->fetchAll(
                'SELECT ' . self::COLUMNS . ' FROM users WHERE organization_id = ? AND is_deleted = 0 '
                . 'ORDER BY id ASC LIMIT ? OFFSET ?',
                [$organizationId, $limit, $offset],
            );
        }

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(?int $organizationId): int
    {
        $row = $organizationId === null
            ? $this->query->fetchOne('SELECT COUNT(*) AS c FROM users WHERE organization_id IS NULL AND is_deleted = 0')
            : $this->query->fetchOne('SELECT COUNT(*) AS c FROM users WHERE organization_id = ? AND is_deleted = 0', [$organizationId]);

        if ($row === null) {
            return 0;
        }

        return (int) ($row['c'] ?? 0);
    }

    public function save(User $user): int
    {
        if ($user->id !== null) {
            $this->query->execute(
                'UPDATE users SET organization_id = ?, email = ?, role = ?, status = ?, password_hash = ? WHERE id = ?',
                [$user->organizationId, $user->email, $user->role->value, $user->status->value, $user->passwordHash, $user->id],
            );

            return $user->id;
        }

        $this->query->execute(
            'INSERT INTO users (organization_id, email, role, status, password_hash, is_deleted) VALUES (?, ?, ?, ?, ?, 0)',
            [$user->organizationId, $user->email, $user->role->value, $user->status->value, $user->passwordHash],
        );

        return $this->query->lastInsertId();
    }

    public function delete(?int $organizationId, int $id, string $deletedAt): void
    {
        // Soft delete — financial/audit context never hard-deletes accounts.
        // `$deletedAt` is supplied by the use case's injected clock so the row
        // and its audit event share one instant (no wall-clock read here).
        if ($organizationId !== null) {
            $this->query->execute(
                'UPDATE users SET is_deleted = 1, deleted_at = ? WHERE id = ? AND organization_id = ?',
                [$deletedAt, $id, $organizationId],
            );
        } else {
            $this->query->execute(
                'UPDATE users SET is_deleted = 1, deleted_at = ? WHERE id = ? AND organization_id IS NULL',
                [$deletedAt, $id],
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        $organizationId = $row['organization_id'] ?? null;

        return new User(
            email: (string) $row['email'],
            role: Role::from((string) $row['role']),
            status: UserStatus::from((string) $row['status']),
            passwordHash: (string) $row['password_hash'],
            organizationId: $organizationId !== null ? (int) $organizationId : null,
            id: (int) $row['id'],
        );
    }
}
