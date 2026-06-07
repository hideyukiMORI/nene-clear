<?php

declare(strict_types=1);

namespace NeneClear\User;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;

final readonly class AcceptInvitationUseCase implements AcceptInvitationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface $users
     * @param Closure(DatabaseQueryExecutorInterface): UserInvitationRepositoryInterface $invitations
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $users,
        private Closure $invitations,
        private Closure $auditRecorder,
        private ClockInterface $clock,
    ) {
    }

    public function execute(AcceptInvitationInput $input): User
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $hash = password_hash($input->password, PASSWORD_BCRYPT);

        // Token validation, password set, invitation consumption, and the audit
        // record commit (or roll back) together. Re-checking inside the
        // transaction also closes a double-accept race.
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($input, $now, $hash): User {
                $invitations = ($this->invitations)($tx);
                $users = ($this->users)($tx);
                $auditRecorder = ($this->auditRecorder)($tx);

                $invitation = $invitations->findByTokenHash(InvitationToken::hash($input->rawToken));
                if ($invitation === null || $invitation->acceptedAt !== null) {
                    throw new InvitationInvalidException();
                }
                if ($invitation->expiresAt < $now) {
                    throw new InvitationExpiredException();
                }

                $existing = $users->findById($invitation->userId);
                if ($existing === null) {
                    throw new InvitationInvalidException();
                }

                $activated = new User(
                    email: $existing->email,
                    role: $existing->role,
                    status: UserStatus::Active,
                    passwordHash: $hash,
                    organizationId: $existing->organizationId,
                    id: $existing->id,
                );
                $users->save($activated);
                $invitations->markAccepted($invitation->id ?? 0, $now);

                // Audit: in-place activation carries before + after. UserResponse
                // never exposes the password hash. The actor is the invitee
                // themselves (the only party holding the token).
                $auditRecorder->record(
                    $existing->organizationId ?? 0,
                    $existing->id ?? 0,
                    $now,
                    'invitation_accepted',
                    'user',
                    $existing->id,
                    [
                        'before' => UserResponse::toArray($existing),
                        'after' => UserResponse::toArray($activated),
                    ],
                );

                return $activated;
            },
        );
    }
}
