<?php

declare(strict_types=1);

namespace NeneClear\User;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class DeleteUserUseCase implements DeleteUserUseCaseInterface
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

    public function execute(int $id, ?int $callerOrganizationId, int $actorUserId): void
    {
        // The soft-delete and its audit record share one transaction (and one
        // instant from the injected clock), so neither can land without the other.
        $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($id, $callerOrganizationId, $actorUserId): void {
                $users = ($this->users)($tx);
                $auditEvents = ($this->auditEvents)($tx);

                $user = $users->findById($id);

                if ($user === null || $user->organizationId !== $callerOrganizationId) {
                    throw new UserNotFoundException($id);
                }

                $now = $this->clock->now()->format('Y-m-d H:i:s');
                $users->delete($callerOrganizationId, $id, $now);

                // Audit: deletion carries the prior state (`before` only) so the removed
                // account's identity and privileges remain reconstructable.
                $auditEvents->record(new AuditEvent(
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
            },
        );
    }
}
