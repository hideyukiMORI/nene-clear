<?php

declare(strict_types=1);

namespace NeneClear\User;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Auth\Role;

final readonly class CreateUserUseCase implements CreateUserUseCaseInterface
{
    /** How long an invitation link stays valid. */
    private const string INVITE_TTL = '+7 days';

    /**
     * @param Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface $users
     * @param Closure(DatabaseQueryExecutorInterface): UserInvitationRepositoryInterface $invitations
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $users,
        private AuditRecorderFactoryInterface $auditFactory,
        private Closure $invitations,
        private InvitationMailerInterface $mailer,
        private InvitationLinkBuilder $linkBuilder,
        private ClockInterface $clock,
    ) {
    }

    public function execute(CreateUserInput $input): User
    {
        // Privilege-escalation guard: the cross-tenant superadmin role may only
        // exist with a null organization (created by a superadmin caller). An
        // org-scoped admin (non-null organizationId) cannot mint a superadmin.
        if ($input->role === Role::Superadmin && $input->organizationId !== null) {
            throw new RoleNotAssignableException($input->role->value);
        }

        // With a password the account is active immediately; without one it is
        // invited — it gets a random placeholder hash (unusable for login) and an
        // invitation token whose raw value is e-mailed to the operator. The
        // account becomes active only once they accept the invite and set a real
        // password (AcceptInvitationUseCase).
        $invited = $input->password === null;
        $status = $invited ? UserStatus::Invited : UserStatus::Active;
        $hash = password_hash($input->password ?? bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $rawToken = $invited ? InvitationToken::newRaw() : null;
        $expiresAt = $this->clock->now()->modify(self::INVITE_TTL)->format('Y-m-d H:i:s');

        // The uniqueness check, user insert, invitation insert, and audit record
        // commit (or roll back) together so a created account can never lack its
        // audit event or its invitation (Issue #122). The e-mail is sent only
        // after the transaction commits.
        $created = $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($input, $status, $hash, $invited, $rawToken, $expiresAt): User {
                $users = ($this->users)($tx);
                $auditRecorder = $this->auditFactory->forExecutor($tx);

                if ($users->existsByEmail($input->email)) {
                    throw new UserAlreadyExistsException($input->email);
                }

                $id = $users->save(new User(
                    email: $input->email,
                    role: $input->role,
                    status: $status,
                    passwordHash: $hash,
                    organizationId: $input->organizationId,
                ));

                $user = $users->findById($id);

                if ($user === null) {
                    throw new UserNotFoundException($id);
                }

                if ($invited && $rawToken !== null) {
                    ($this->invitations)($tx)->save(new UserInvitation(
                        organizationId: $input->organizationId,
                        userId: $user->id ?? $id,
                        tokenHash: InvitationToken::hash($rawToken),
                        expiresAt: $expiresAt,
                    ));
                }

                // Audit: account creation carries `after` only (no prior state). The
                // snapshot reuses UserResponse, so the password hash is never recorded.
                $auditRecorder->record(new AuditEvent(
                    action: 'user_created',
                    entityType: 'user',
                    entityId: $user->id,
                    actorId: $input->actorUserId,
                    organizationId: $input->organizationId ?? 0,
                    occurredAt: $this->clock->now()->format('Y-m-d H:i:s'),
                    after: UserResponse::toArray($user),
                ));

                return $user;
            },
        );

        if ($invited && $rawToken !== null) {
            $this->sendInvitation($created, $rawToken);
        }

        return $created;
    }

    private function sendInvitation(User $user, string $rawToken): void
    {
        $link = $this->linkBuilder->forToken($rawToken);
        $body = "NeNe Clear への招待が届いています。\n\n"
            . "以下のリンクからパスワードを設定すると、ログインできるようになります（リンクの有効期限は7日間です）。\n\n"
            . $link . "\n\n"
            . "心当たりがない場合は、このメールを破棄してください。\n";

        $this->mailer->send(new InvitationMailPayload(
            userId: $user->id ?? 0,
            organizationId: $user->organizationId,
            to: $user->email,
            subject: 'NeNe Clear への招待',
            body: $body,
            acceptUrl: $link,
        ));
    }
}
