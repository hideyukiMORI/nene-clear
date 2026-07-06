<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;

/**
 * Confirms a TOTP enrolment: verifies a code against the pending secret, enables
 * it, issues a fresh set of one-time recovery codes, and audits `mfa_enabled`.
 *
 * Code verification (which records failures for lockout) runs *outside* the
 * business transaction so a wrong code's failure count is never rolled back.
 */
final readonly class EnableTotpUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): TotpSecretRepositoryInterface $secrets
     * @param Closure(DatabaseQueryExecutorInterface): RecoveryCodeRepositoryInterface $recovery
     */
    public function __construct(
        private TotpAuthenticator $authenticator,
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $secrets,
        private Closure $recovery,
        private AuditRecorderFactoryInterface $auditFactory,
        private RecoveryCodeService $recoveryService,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<string> the plaintext recovery codes (return to the user once)
     *
     * @throws TotpAlreadyEnabledException|TotpNotEnabledException|TotpLockedException|TotpInvalidCodeException
     */
    public function execute(int $userId, int $organizationId, string $code): array
    {
        if ($this->authenticator->isEnabled($userId)) {
            throw new TotpAlreadyEnabledException();
        }

        // Verify the pending secret (records failures / lockout) outside the transaction.
        $this->authenticator->verifyPending($userId, $code);

        $codes = $this->recoveryService->generate();
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $this->transactionManager->transactional(function (DatabaseQueryExecutorInterface $ex) use ($userId, $organizationId, $codes, $now): void {
            ($this->secrets)($ex)->setEnabled($userId, true);
            ($this->recovery)($ex)->replaceForUser($userId, $codes['hashes'], $now);
            $this->auditFactory->forExecutor($ex)->record(new AuditEvent(
                action: 'mfa_enabled',
                entityType: 'user',
                entityId: $userId,
                actorId: $userId,
                organizationId: $organizationId,
                occurredAt: $now,
                after: ['mfa_enabled' => true],
            ));
        });

        return $codes['plain'];
    }
}
