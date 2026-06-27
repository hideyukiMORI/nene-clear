<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneClear\Security\Encryptor;

final readonly class PdoTotpSecretRepository implements TotpSecretRepositoryInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private ?Encryptor $encryptor = null,
    ) {
    }

    public function findByUser(int $userId): ?TotpSecret
    {
        $row = $this->query->fetchOne(
            'SELECT user_id, secret, is_enabled, failed_attempts, locked_until FROM totp_secrets WHERE user_id = ?',
            [$userId],
        );
        if ($row === null) {
            return null;
        }

        return new TotpSecret(
            userId: (int) $row['user_id'],
            secret: $this->encryptor?->decrypt((string) $row['secret']) ?? (string) $row['secret'],
            isEnabled: (bool) (int) $row['is_enabled'],
            failedAttempts: (int) $row['failed_attempts'],
            lockedUntil: $row['locked_until'] !== null ? (string) $row['locked_until'] : null,
        );
    }

    public function upsert(TotpSecret $secret, string $now): void
    {
        $this->query->execute('DELETE FROM totp_secrets WHERE user_id = ?', [$secret->userId]);
        $this->query->insert(
            'INSERT INTO totp_secrets (user_id, secret, is_enabled, failed_attempts, locked_until, created_at) VALUES (?, ?, ?, 0, NULL, ?)',
            [
                $secret->userId,
                $this->encryptor?->encrypt($secret->secret) ?? $secret->secret,
                $secret->isEnabled ? 1 : 0,
                $now,
            ],
        );
    }

    public function setEnabled(int $userId, bool $enabled): void
    {
        $this->query->execute('UPDATE totp_secrets SET is_enabled = ? WHERE user_id = ?', [$enabled ? 1 : 0, $userId]);
    }

    public function recordFailure(int $userId, int $failedAttempts, ?string $lockedUntil): void
    {
        $this->query->execute(
            'UPDATE totp_secrets SET failed_attempts = ?, locked_until = ? WHERE user_id = ?',
            [$failedAttempts, $lockedUntil, $userId],
        );
    }

    public function resetFailures(int $userId): void
    {
        $this->query->execute('UPDATE totp_secrets SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?', [$userId]);
    }

    public function deleteByUser(int $userId): void
    {
        $this->query->execute('DELETE FROM totp_secrets WHERE user_id = ?', [$userId]);
    }
}
