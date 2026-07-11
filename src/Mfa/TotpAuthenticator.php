<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use DateInterval;
use DateTimeImmutable;
use Nene2\Auth\TotpAuthenticator as Rfc6238Totp;
use Nene2\Http\ClockInterface;

/**
 * TOTP verification policy: replay protection and brute-force lockout around
 * the framework RFC 6238 primitive ({@see Rfc6238Totp}, #292) — shared by
 * enrolment confirmation and login.
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
        private Rfc6238Totp $totp,
        private ClockInterface $clock,
    ) {
    }

    public function isEnabled(int $userId): bool
    {
        $secret = $this->secrets->findByUser($userId);

        return $secret !== null && $secret->isEnabled;
    }

    /**
     * Verify against an **enabled** enrolment (login, disable).
     *
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

        $this->verifyAgainst($userId, $secret, $code);
    }

    /**
     * Verify against a **pending** (set-up but not yet enabled) enrolment — used
     * to confirm enrolment. Same lockout/replay protection as {@see self::verify}.
     *
     * @throws TotpNotEnabledException when there is no enrolment to confirm
     * @throws TotpLockedException|TotpInvalidCodeException
     */
    public function verifyPending(int $userId, string $code): void
    {
        $secret = $this->secrets->findByUser($userId);
        if ($secret === null) {
            throw new TotpNotEnabledException();
        }

        $this->verifyAgainst($userId, $secret, $code);
    }

    private function verifyAgainst(int $userId, TotpSecret $secret, string $code): void
    {
        $now = $this->clock->now();
        $lockExpired = $secret->lockedUntil !== null && $now >= new DateTimeImmutable($secret->lockedUntil);
        if ($secret->lockedUntil !== null && !$lockExpired) {
            throw new TotpLockedException($secret->lockedUntil);
        }

        // The upstream RFC 6238 primitive owns the cryptography (#292); the
        // clock's timestamp keeps step evaluation deterministic in tests.
        $step = $this->totp->verify($secret->secret, $code, $now->getTimestamp());
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
