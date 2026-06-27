<?php

declare(strict_types=1);

namespace NeneClear\Tests\Mfa;

use NeneClear\Mfa\UsedTotpStepRepositoryInterface;

final class InMemoryUsedTotpStepRepository implements UsedTotpStepRepositoryInterface
{
    /** @var array<string, true> */
    public array $used = [];

    public function isStepUsed(int $userId, int $timeStep): bool
    {
        return isset($this->used[$userId . ':' . $timeStep]);
    }

    public function markStepUsed(int $userId, int $timeStep, string $usedAt): void
    {
        $this->used[$userId . ':' . $timeStep] = true;
    }

    public function deleteByUser(int $userId): void
    {
        foreach (array_keys($this->used) as $key) {
            if (str_starts_with($key, $userId . ':')) {
                unset($this->used[$key]);
            }
        }
    }
}
