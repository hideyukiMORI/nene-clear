<?php

declare(strict_types=1);

namespace NeneClear\User;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;
use NeneClear\Auth\Role;

final readonly class CreateUserUseCase implements CreateUserUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface $users
     * @param Closure(DatabaseQueryExecutorInterface): AuditEventRepositoryInterface $auditEvents
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $users,
        private Closure $auditEvents,
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

        // With a password the account is active; without one it is invited and
        // cannot log in until a password is set (the hash is a random placeholder).
        $status = $input->password !== null ? UserStatus::Active : UserStatus::Invited;
        $hash = password_hash($input->password ?? bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        // The uniqueness check, insert, and audit record commit (or roll back)
        // together so a created account can never lack its audit event (Issue #122).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($input, $status, $hash): User {
                $users = ($this->users)($tx);
                $auditEvents = ($this->auditEvents)($tx);

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

                $created = $users->findById($id);

                if ($created === null) {
                    throw new UserNotFoundException($id);
                }

                // Audit: account creation carries `after` only (no prior state). The
                // password hash is never recorded — only who/what changed.
                $auditEvents->record(new AuditEvent(
                    organizationId: $input->organizationId ?? 0,
                    eventType: 'user_created',
                    actorUserId: $input->actorUserId,
                    occurredAt: $this->clock->now()->format('Y-m-d H:i:s'),
                    payload: [
                        'after' => [
                            'user_id' => $created->id,
                            'email' => $created->email,
                            'role' => $created->role->value,
                            'status' => $created->status->value,
                            'organization_id' => $created->organizationId,
                        ],
                    ],
                ));

                return $created;
            },
        );
    }
}
