<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class DeleteUserUseCase implements DeleteUserUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(int $id, ?int $callerOrganizationId, int $actorUserId): void
    {
        $user = $this->users->findById($id);

        if ($user === null || $user->organizationId !== $callerOrganizationId) {
            throw new UserNotFoundException($id);
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->users->delete($callerOrganizationId, $id, $now);

        // Audit: deletion carries the prior state (`before` only) so the removed
        // account's identity and privileges remain reconstructable.
        $this->auditEvents->record(new AuditEvent(
            organizationId: $user->organizationId ?? 0,
            eventType: 'user_deleted',
            actorUserId: $actorUserId,
            occurredAt: $now,
            payload: [
                'before' => [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'status' => $user->status->value,
                    'organization_id' => $user->organizationId,
                ],
            ],
        ));
    }
}
