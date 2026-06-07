<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\ClockInterface;

final readonly class GetInvitationUseCase implements GetInvitationUseCaseInterface
{
    public function __construct(
        private UserInvitationRepositoryInterface $invitations,
        private UserRepositoryInterface $users,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $rawToken): string
    {
        $invitation = $this->resolve($rawToken);

        $user = $this->users->findById($invitation->userId);
        if ($user === null) {
            throw new InvitationInvalidException();
        }

        return $user->email;
    }

    /**
     * Look up and validate the token (shared by accept). Unknown or already
     * accepted → invalid (no enumeration); past expiry → expired.
     */
    private function resolve(string $rawToken): UserInvitation
    {
        $invitation = $this->invitations->findByTokenHash(InvitationToken::hash($rawToken));
        if ($invitation === null || $invitation->acceptedAt !== null) {
            throw new InvitationInvalidException();
        }

        if ($invitation->expiresAt < $this->clock->now()->format('Y-m-d H:i:s')) {
            throw new InvitationExpiredException();
        }

        return $invitation;
    }
}
