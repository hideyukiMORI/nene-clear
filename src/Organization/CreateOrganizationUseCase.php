<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class CreateOrganizationUseCase implements CreateOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(CreateOrganizationInput $input): CreateOrganizationOutput
    {
        if ($this->organizations->existsBySlug($input->slug)) {
            throw new OrganizationAlreadyExistsException($input->slug);
        }

        $id = $this->organizations->save(new Organization(slug: $input->slug, name: $input->name));

        // Audit: a tenant lifecycle event is scoped to the affected tenant, so it
        // surfaces in that organization's own audit trail.
        $this->auditEvents->record(new AuditEvent(
            organizationId: $id,
            eventType: 'organization_created',
            actorUserId: $input->actorUserId,
            occurredAt: $this->clock->now()->format('Y-m-d H:i:s'),
            payload: [
                'after' => [
                    'organization_id' => $id,
                    'slug' => $input->slug,
                    'name' => $input->name,
                ],
            ],
        ));

        return new CreateOrganizationOutput(id: $id, slug: $input->slug, name: $input->name);
    }
}
