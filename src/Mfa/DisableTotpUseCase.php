<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;

/**
 * Disables a user's TOTP, after proving control with either a current TOTP code
 * or a valid recovery code, then deletes the secret, used steps, and recovery
 * codes and audits `mfa_disabled`. A recovery code is tried first so using one
 * does not count against the TOTP lockout.
 */
final readonly class DisableTotpUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): TotpSecretRepositoryInterface $secrets
     * @param Closure(DatabaseQueryExecutorInterface): UsedTotpStepRepositoryInterface $usedSteps
     * @param Closure(DatabaseQueryExecutorInterface): RecoveryCodeRepositoryInterface $recovery
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     */
    public function __construct(
        private TotpAuthenticator $authenticator,
        private DatabaseQueryExecutorInterface $reader,
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $secrets,
        private Closure $usedSteps,
        private Closure $recovery,
        private Closure $auditRecorder,
        private RecoveryCodeService $recoveryService,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws TotpNotEnabledException|TotpLockedException|TotpInvalidCodeException
     */
    public function execute(int $userId, int $organizationId, string $code): void
    {
        if (!$this->authenticator->isEnabled($userId)) {
            throw new TotpNotEnabledException();
        }

        // A recovery code (tried first, no TOTP-lockout side effect) or a TOTP code.
        $unused = ($this->recovery)($this->reader)->findUnusedByUser($userId);
        if ($this->recoveryService->match($code, $unused) === null) {
            $this->authenticator->verify($userId, $code);
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $this->transactionManager->transactional(function (DatabaseQueryExecutorInterface $ex) use ($userId, $organizationId, $now): void {
            ($this->usedSteps)($ex)->deleteByUser($userId);
            ($this->recovery)($ex)->deleteByUser($userId);
            ($this->secrets)($ex)->deleteByUser($userId);
            ($this->auditRecorder)($ex)->record(
                $organizationId,
                $userId,
                $now,
                'mfa_disabled',
                'user',
                $userId,
                ['before' => ['mfa_enabled' => true], 'after' => ['mfa_enabled' => false]],
            );
        });
    }
}
