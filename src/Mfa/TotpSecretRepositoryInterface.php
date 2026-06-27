<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

interface TotpSecretRepositoryInterface
{
    public function findByUser(int $userId): ?TotpSecret;

    /** Insert or replace the user's secret (used by setup; re-setup overwrites). */
    public function upsert(TotpSecret $secret, string $now): void;

    public function setEnabled(int $userId, bool $enabled): void;

    public function recordFailure(int $userId, int $failedAttempts, ?string $lockedUntil): void;

    public function resetFailures(int $userId): void;

    public function deleteByUser(int $userId): void;
}
