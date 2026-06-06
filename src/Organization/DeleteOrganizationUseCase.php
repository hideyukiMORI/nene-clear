<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizations
     * @param Closure(DatabaseQueryExecutorInterface): AuditEventRepositoryInterface $auditEvents
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $organizations,
        private Closure $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(int $id, int $actorUserId): void
    {
        // The soft-delete and its audit record share one transaction (and one
        // instant from the injected clock), so neither can land without the other.
        $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($id, $actorUserId): void {
                $organizations = ($this->organizations)($tx);
                $auditEvents = ($this->auditEvents)($tx);

                $organization = $organizations->findById($id);

                if ($organization === null) {
                    throw new OrganizationNotFoundException($id);
                }

                $now = $this->clock->now()->format('Y-m-d H:i:s');
                $organizations->delete($id, $now);

                // Audit: deletion carries the prior state (`before` only), scoped to the
                // now-removed tenant.
                $auditEvents->record(new AuditEvent(
                    organizationId: $id,
                    eventType: 'organization_deleted',
                    actorUserId: $actorUserId,
                    occurredAt: $now,
                    payload: [
                        'before' => [
                            'organization_id' => $id,
                            'slug' => $organization->slug,
                            'name' => $organization->name,
                        ],
                    ],
                ));
            },
        );
    }
}
