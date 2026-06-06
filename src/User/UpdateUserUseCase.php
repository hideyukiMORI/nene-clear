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

final readonly class UpdateUserUseCase implements UpdateUserUseCaseInterface
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

    public function execute(UpdateUserInput $input): User
    {
        // The read, update, and audit record share one transaction so the recorded
        // before/after always matches what was persisted (Issue #122).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($input): User {
                $users = ($this->users)($tx);
                $auditEvents = ($this->auditEvents)($tx);

                $existing = $users->findById($input->id);

                if ($existing === null || $existing->organizationId !== $input->callerOrganizationId) {
                    throw new UserNotFoundException($input->id);
                }

                // Privilege-escalation guard: an org-scoped user (non-null org) can never
                // be promoted to the cross-tenant superadmin role.
                if ($input->role === Role::Superadmin && $existing->organizationId !== null) {
                    throw new RoleNotAssignableException($input->role->value);
                }

                $updated = new User(
                    email: $existing->email,
                    role: $input->role ?? $existing->role,
                    status: $input->status ?? $existing->status,
                    passwordHash: $existing->passwordHash,
                    organizationId: $existing->organizationId,
                    id: $existing->id,
                );

                $users->save($updated);

                // Audit: in-place change carries both the prior and the resulting role
                // and status, so a role escalation or deactivation is fully reconstructable.
                $auditEvents->record(new AuditEvent(
                    organizationId: $existing->organizationId ?? 0,
                    eventType: 'user_updated',
                    actorUserId: $input->actorUserId,
                    occurredAt: $this->clock->now()->format('Y-m-d H:i:s'),
                    payload: [
                        'user_id' => $existing->id,
                        'email' => $existing->email,
                        'before' => [
                            'role' => $existing->role->value,
                            'status' => $existing->status->value,
                        ],
                        'after' => [
                            'role' => $updated->role->value,
                            'status' => $updated->status->value,
                        ],
                    ],
                ));

                return $updated;
            },
        );
    }
}
