<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Mfa\TotpAuthenticator;
use NeneClear\User\UserRepositoryInterface;
use NeneClear\User\UserStatus;

final readonly class LoginUseCase implements LoginUseCaseInterface
{
    /**
     * A valid bcrypt hash used to equalize timing when the email is unknown,
     * so a failed login does not reveal whether the account exists.
     */
    private const string TIMING_EQUALIZER_HASH = '$2y$12$zIm0IdtQKFLbeCP4lZhm7upwJ7hz/JAj4krfZ53eGCIVzLq82RwP6';

    public function __construct(
        private UserRepositoryInterface $users,
        private TokenIssuerInterface $tokens,
        private AuditRecorderInterface $auditRecorder,
        private ClockInterface $clock,
        private TotpAuthenticator $mfa,
        private MfaChallengeTokens $challenges,
    ) {
    }

    public function execute(LoginInput $input): LoginOutput
    {
        $user = $this->users->findByEmail($input->email);

        $hash = $user !== null ? $user->passwordHash : self::TIMING_EQUALIZER_HASH;
        $passwordMatches = password_verify($input->password, $hash);

        if ($user === null || $user->status !== UserStatus::Active || !$passwordMatches) {
            // Audit a rejected attempt. The actor is unknown (0) and the event is
            // not tenant-scoped (organization 0): recording the would-be org —
            // like recording the password — could leak whether the account
            // exists. Only the attempted email and a coarse reason are kept.
            $this->auditRecorder->record(new AuditEvent(
                action: 'login_failed',
                entityType: 'user',
                entityId: null,
                actorId: 0,
                organizationId: 0,
                occurredAt: $this->clock->now()->format('Y-m-d H:i:s'),
                after: [
                    'email' => $input->email,
                    'failure_reason' => 'invalid_credentials',
                ],
            ));

            throw new InvalidCredentialsException();
        }

        $userId = (int) $user->id;

        // Password is correct. If the user has TOTP enabled the login is not yet
        // complete: hand out a short-lived challenge instead of a session token,
        // and defer the login_succeeded audit until the code is verified
        // (see VerifyMfaLoginUseCase).
        if ($this->mfa->isEnabled($userId)) {
            return new LoginOutput(
                token: null,
                userId: $userId,
                email: $user->email,
                role: $user->role->value,
                organizationId: $user->organizationId,
                mfaRequired: true,
                mfaToken: $this->challenges->issue($userId),
            );
        }

        $this->auditRecorder->record(new AuditEvent(
            action: 'login_succeeded',
            entityType: 'user',
            entityId: $userId,
            actorId: $userId,
            organizationId: $user->organizationId ?? 0,
            occurredAt: $this->clock->now()->format('Y-m-d H:i:s'),
            after: [
                'user_id' => $userId,
                'email' => $user->email,
            ],
        ));

        return new LoginOutput(
            token: $this->tokens->issueForUser($user),
            userId: $userId,
            email: $user->email,
            role: $user->role->value,
            organizationId: $user->organizationId,
        );
    }
}
