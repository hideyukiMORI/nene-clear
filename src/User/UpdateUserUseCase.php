<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;
use NeneClear\Auth\Role;

final readonly class UpdateUserUseCase implements UpdateUserUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(UpdateUserInput $input): User
    {
        $existing = $this->users->findById($input->id);

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

        $this->users->save($updated);

        // Audit: in-place change carries both the prior and the resulting role
        // and status, so a role escalation or deactivation is fully reconstructable.
        $this->auditEvents->record(new AuditEvent(
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
    }
}
