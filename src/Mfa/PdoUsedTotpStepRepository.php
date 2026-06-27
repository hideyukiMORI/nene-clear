<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoUsedTotpStepRepository implements UsedTotpStepRepositoryInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function isStepUsed(int $userId, int $timeStep): bool
    {
        return $this->query->fetchOne(
            'SELECT id FROM used_totp_steps WHERE user_id = ? AND time_step = ?',
            [$userId, $timeStep],
        ) !== null;
    }

    public function markStepUsed(int $userId, int $timeStep, string $usedAt): void
    {
        $this->query->insert(
            'INSERT INTO used_totp_steps (user_id, time_step, used_at) VALUES (?, ?, ?)',
            [$userId, $timeStep, $usedAt],
        );
    }

    public function deleteByUser(int $userId): void
    {
        $this->query->execute('DELETE FROM used_totp_steps WHERE user_id = ?', [$userId]);
    }
}
