<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoRecoveryCodeRepository implements RecoveryCodeRepositoryInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function replaceForUser(int $userId, array $hashes, string $createdAt): void
    {
        $this->query->execute('DELETE FROM recovery_codes WHERE user_id = ?', [$userId]);
        foreach ($hashes as $hash) {
            $this->query->insert(
                'INSERT INTO recovery_codes (user_id, code_hash, used_at, created_at) VALUES (?, ?, NULL, ?)',
                [$userId, $hash, $createdAt],
            );
        }
    }

    public function findUnusedByUser(int $userId): array
    {
        $rows = $this->query->fetchAll('SELECT id, code_hash FROM recovery_codes WHERE user_id = ? AND used_at IS NULL', [$userId]);

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'code_hash' => (string) $row['code_hash']],
            $rows,
        );
    }

    public function markUsed(int $id, string $usedAt): void
    {
        $this->query->execute('UPDATE recovery_codes SET used_at = ? WHERE id = ?', [$usedAt, $id]);
    }

    public function countUnused(int $userId): int
    {
        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM recovery_codes WHERE user_id = ? AND used_at IS NULL', [$userId]);

        return $row !== null ? (int) $row['c'] : 0;
    }

    public function deleteByUser(int $userId): void
    {
        $this->query->execute('DELETE FROM recovery_codes WHERE user_id = ?', [$userId]);
    }
}
