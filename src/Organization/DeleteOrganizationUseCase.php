<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(int $id, int $actorUserId): void
    {
        $organization = $this->organizations->findById($id);

        if ($organization === null) {
            throw new OrganizationNotFoundException($id);
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->organizations->delete($id, $now);

        // Audit: deletion carries the prior state (`before` only), scoped to the
        // now-removed tenant. The audit event and the soft-delete row share one
        // instant from the injected clock.
        $this->auditEvents->record(new AuditEvent(
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
    }
}
