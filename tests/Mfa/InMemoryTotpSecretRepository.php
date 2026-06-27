<?php

declare(strict_types=1);

namespace NeneClear\Tests\Mfa;

use NeneClear\Mfa\TotpSecret;
use NeneClear\Mfa\TotpSecretRepositoryInterface;

final class InMemoryTotpSecretRepository implements TotpSecretRepositoryInterface
{
    /** @var array<int, TotpSecret> */
    public array $byUser = [];

    public function findByUser(int $userId): ?TotpSecret
    {
        return $this->byUser[$userId] ?? null;
    }

    public function upsert(TotpSecret $secret, string $now): void
    {
        $this->byUser[$secret->userId] = $secret;
    }

    public function setEnabled(int $userId, bool $enabled): void
    {
        $s = $this->byUser[$userId] ?? null;
        if ($s !== null) {
            $this->byUser[$userId] = new TotpSecret($s->userId, $s->secret, $enabled, $s->failedAttempts, $s->lockedUntil);
        }
    }

    public function recordFailure(int $userId, int $failedAttempts, ?string $lockedUntil): void
    {
        $s = $this->byUser[$userId] ?? null;
        if ($s !== null) {
            $this->byUser[$userId] = new TotpSecret($s->userId, $s->secret, $s->isEnabled, $failedAttempts, $lockedUntil);
        }
    }

    public function resetFailures(int $userId): void
    {
        $s = $this->byUser[$userId] ?? null;
        if ($s !== null) {
            $this->byUser[$userId] = new TotpSecret($s->userId, $s->secret, $s->isEnabled, 0, null);
        }
    }

    public function deleteByUser(int $userId): void
    {
        unset($this->byUser[$userId]);
    }
}
