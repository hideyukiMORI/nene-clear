<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use DateInterval;
use DateTimeImmutable;
use Nene2\Http\ClockInterface;

/**
 * Verifies a TOTP code with replay protection and brute-force lockout — the
 * security core shared by enrolment confirmation and (later) login.
 *
 * Order matters: a locked enrolment is rejected *before* the code is checked, so
 * the response time can't be used as a timing oracle. A matched-but-already-used
 * step is treated as a failure (replay). After {@see self::MAX_FAILURES} failures
 * the enrolment is locked for {@see self::LOCK_MINUTES}; once that window passes
 * the failure counter starts fresh.
 */
final readonly class TotpAuthenticator
{
    private const int MAX_FAILURES = 3;
    private const int LOCK_MINUTES = 15;

    public function __construct(
        private TotpSecretRepositoryInterface $secrets,
        private UsedTotpStepRepositoryInterface $usedSteps,
        private TotpGenerator $generator,
        private ClockInterface $clock,
    ) {
    }

    public function isEnabled(int $userId): bool
    {
        $secret = $this->secrets->findByUser($userId);

        return $secret !== null && $secret->isEnabled;
    }

    /**
     * @throws TotpNotEnabledException when there is no enabled enrolment
     * @throws TotpLockedException     when locked out (code is not checked)
     * @throws TotpInvalidCodeException when the code is wrong or replayed
     */
    public function verify(int $userId, string $code): void
    {
        $secret = $this->secrets->findByUser($userId);
        if ($secret === null || !$secret->isEnabled) {
            throw new TotpNotEnabledException();
        }

        $now = $this->clock->now();
        $lockExpired = $secret->lockedUntil !== null && $now >= new DateTimeImmutable($secret->lockedUntil);
        if ($secret->lockedUntil !== null && !$lockExpired) {
            throw new TotpLockedException($secret->lockedUntil);
        }

        $step = $this->generator->verify($secret->secret, trim($code));
        if ($step === null || $this->usedSteps->isStepUsed($userId, $step)) {
            $this->registerFailure($userId, $lockExpired ? 0 : $secret->failedAttempts, $now);
            throw new TotpInvalidCodeException();
        }

        $this->usedSteps->markStepUsed($userId, $step, $now->format('Y-m-d H:i:s'));
        $this->secrets->resetFailures($userId);
    }

    private function registerFailure(int $userId, int $priorFailures, DateTimeImmutable $now): void
    {
        $attempts = $priorFailures + 1;
        $lockedUntil = $attempts >= self::MAX_FAILURES
            ? $now->add(new DateInterval('PT' . self::LOCK_MINUTES . 'M'))->format('Y-m-d H:i:s')
            : null;

        $this->secrets->recordFailure($userId, $attempts, $lockedUntil);
    }
}
