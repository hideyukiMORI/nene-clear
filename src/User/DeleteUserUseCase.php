<?php

declare(strict_types=1);

namespace NeneClear\User;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;

final readonly class DeleteUserUseCase implements DeleteUserUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface $users
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $users,
        private AuditRecorderFactoryInterface $auditFactory,
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
                $auditRecorder = $this->auditFactory->forExecutor($tx);

                $user = $users->findById($id);

                if ($user === null || $user->organizationId !== $callerOrganizationId) {
                    throw new UserNotFoundException($id);
                }

                $now = $this->clock->now()->format('Y-m-d H:i:s');
                $users->delete($callerOrganizationId, $id, $now);

                // Audit: deletion carries the prior state (`before` only) so the removed
                // account's identity and privileges remain reconstructable.
                $auditRecorder->record(new AuditEvent(
                    action: 'user_deleted',
                    entityType: 'user',
                    entityId: $user->id,
                    actorId: $actorUserId,
                    organizationId: $user->organizationId ?? 0,
                    occurredAt: $now,
                    before: UserResponse::toArray($user),
                ));
            },
        );
    }
}
