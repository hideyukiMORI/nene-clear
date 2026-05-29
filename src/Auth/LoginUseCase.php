<?php

declare(strict_types=1);

namespace NeneClear\Auth;

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
    ) {
    }

    public function execute(LoginInput $input): LoginOutput
    {
        $user = $this->users->findByEmail($input->email);

        $hash = $user !== null ? $user->passwordHash : self::TIMING_EQUALIZER_HASH;
        $passwordMatches = password_verify($input->password, $hash);

        if ($user === null || $user->status !== UserStatus::Active || !$passwordMatches) {
            throw new InvalidCredentialsException();
        }

        return new LoginOutput(
            token: $this->tokens->issueForUser($user),
            userId: (int) $user->id,
            email: $user->email,
            role: $user->role->value,
            organizationId: $user->organizationId,
        );
    }
}
