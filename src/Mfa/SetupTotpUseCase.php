<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\User\UserRepositoryInterface;

/**
 * Generates a fresh (not-yet-enabled) TOTP secret for the user and returns it
 * plus the `otpauth://` URI to scan. Re-running setup overwrites any prior
 * secret and clears its used steps and recovery codes, so old codes stop working.
 */
final readonly class SetupTotpUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): TotpSecretRepositoryInterface $secrets
     * @param Closure(DatabaseQueryExecutorInterface): UsedTotpStepRepositoryInterface $usedSteps
     * @param Closure(DatabaseQueryExecutorInterface): RecoveryCodeRepositoryInterface $recovery
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $secrets,
        private Closure $usedSteps,
        private Closure $recovery,
        private UserRepositoryInterface $users,
        private TotpGenerator $generator,
        private ClockInterface $clock,
    ) {
    }

    /** @return array{secret: string, otpauth_uri: string} */
    public function execute(int $userId): array
    {
        $user = $this->users->findById($userId);
        $account = $user !== null ? $user->email : ('user-' . $userId);
        $secret = $this->generator->generateSecret();
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $this->transactionManager->transactional(function (DatabaseQueryExecutorInterface $ex) use ($userId, $secret, $now): void {
            ($this->usedSteps)($ex)->deleteByUser($userId);
            ($this->recovery)($ex)->deleteByUser($userId);
            ($this->secrets)($ex)->upsert(new TotpSecret($userId, $secret, false), $now);
        });

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->generator->buildOtpAuthUri($secret, $account),
        ];
    }
}
