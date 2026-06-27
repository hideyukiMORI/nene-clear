<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

interface RecoveryCodeRepositoryInterface
{
    /**
     * Replace the user's full set of recovery codes (delete all, insert the new hashes).
     *
     * @param list<string> $hashes
     */
    public function replaceForUser(int $userId, array $hashes, string $createdAt): void;

    /** @return list<array{id: int, code_hash: string}> */
    public function findUnusedByUser(int $userId): array;

    public function markUsed(int $id, string $usedAt): void;

    public function countUnused(int $userId): int;

    public function deleteByUser(int $userId): void;
}
