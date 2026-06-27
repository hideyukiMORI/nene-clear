<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

/** Records consumed TOTP time steps so a code cannot be replayed within its window. */
interface UsedTotpStepRepositoryInterface
{
    public function isStepUsed(int $userId, int $timeStep): bool;

    public function markStepUsed(int $userId, int $timeStep, string $usedAt): void;

    public function deleteByUser(int $userId): void;
}
