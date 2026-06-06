<?php

declare(strict_types=1);

namespace NeneClear\User;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;
use NeneClear\Auth\Role;

final readonly class CreateUserUseCase implements CreateUserUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface $users
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $users,
        private Closure $auditRecorder,
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
                $auditRecorder = ($this->auditRecorder)($tx);

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
                // snapshot reuses UserResponse, so the password hash is never recorded.
                $auditRecorder->record(
                    $input->organizationId ?? 0,
                    $input->actorUserId,
                    $this->clock->now()->format('Y-m-d H:i:s'),
                    'user_created',
                    'user',
                    $created->id,
                    ['after' => UserResponse::toArray($created)],
                );

                return $created;
            },
        );
    }
}
