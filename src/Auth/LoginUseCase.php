<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;
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
            $this->auditRecorder->record(
                0,
                0,
                $this->clock->now()->format('Y-m-d H:i:s'),
                'login_failed',
                'user',
                null,
                [
                    'after' => [
                        'email' => $input->email,
                        'failure_reason' => 'invalid_credentials',
                    ],
                ],
            );

            throw new InvalidCredentialsException();
        }

        $this->auditRecorder->record(
            $user->organizationId ?? 0,
            (int) $user->id,
            $this->clock->now()->format('Y-m-d H:i:s'),
            'login_succeeded',
            'user',
            (int) $user->id,
            [
                'after' => [
                    'user_id' => (int) $user->id,
                    'email' => $user->email,
                ],
            ],
        );

        return new LoginOutput(
            token: $this->tokens->issueForUser($user),
            userId: (int) $user->id,
            email: $user->email,
            role: $user->role->value,
            organizationId: $user->organizationId,
        );
    }
}
