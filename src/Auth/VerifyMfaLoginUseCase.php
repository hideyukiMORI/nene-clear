<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Mfa\RecoveryCodeRepositoryInterface;
use NeneClear\Mfa\RecoveryCodeService;
use NeneClear\Mfa\TotpAuthenticator;
use NeneClear\User\UserRepositoryInterface;
use NeneClear\User\UserStatus;

/**
 * Second leg of MFA login: verifies the challenge token from the password step,
 * checks a TOTP code (or a recovery code), records the deferred `login_succeeded`
 * audit, and finally issues the session token. Code verification (lockout/replay)
 * runs outside the transaction so a wrong code's failure count is never rolled back.
 */
final readonly class VerifyMfaLoginUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): RecoveryCodeRepositoryInterface $recovery
     */
    public function __construct(
        private MfaChallengeTokens $challenges,
        private UserRepositoryInterface $users,
        private TotpAuthenticator $authenticator,
        private RecoveryCodeService $recoveryService,
        private DatabaseQueryExecutorInterface $reader,
        private SessionTokens $tokens,
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $recovery,
        private AuditRecorderFactoryInterface $auditFactory,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $mfaToken, string $code): LoginOutput
    {
        $userId = $this->challenges->verifyUserId($mfaToken);
        $user = $this->users->findById($userId);
        if ($user === null || $user->status !== UserStatus::Active || !$this->authenticator->isEnabled($userId)) {
            throw new MfaChallengeInvalidException();
        }

        // A recovery code (tried first, no TOTP-lockout side effect) or a TOTP code.
        $unused = ($this->recovery)($this->reader)->findUnusedByUser($userId);
        $recoveryId = $this->recoveryService->match($code, $unused);
        if ($recoveryId === null) {
            $this->authenticator->verify($userId, $code);
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->transactionManager->transactional(function (DatabaseQueryExecutorInterface $ex) use ($userId, $user, $recoveryId, $now): void {
            if ($recoveryId !== null) {
                ($this->recovery)($ex)->markUsed($recoveryId, $now);
            }
            $this->auditFactory->forExecutor($ex)->record(new AuditEvent(
                action: 'login_succeeded',
                entityType: 'user',
                entityId: $userId,
                actorId: $userId,
                organizationId: $user->organizationId ?? 0,
                occurredAt: $now,
                after: ['user_id' => $userId, 'email' => $user->email, 'mfa' => true],
            ));
        });

        return new LoginOutput(
            token: $this->tokens->issueForUser($user),
            userId: $userId,
            email: $user->email,
            role: $user->role->value,
            organizationId: $user->organizationId,
        );
    }
}
