<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;

final readonly class CreateOrganizationUseCase implements CreateOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizations
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $organizations,
        private AuditRecorderFactoryInterface $auditFactory,
        private ClockInterface $clock,
    ) {
    }

    public function execute(CreateOrganizationInput $input): CreateOrganizationOutput
    {
        // The duplicate-slug check, the insert, and the audit record commit (or
        // roll back) together so a change can never be persisted without its audit
        // event (Issue #122). Repositories are built from the transaction executor.
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($input): CreateOrganizationOutput {
                $organizations = ($this->organizations)($tx);
                $auditRecorder = $this->auditFactory->forExecutor($tx);

                if ($organizations->existsBySlug($input->slug)) {
                    throw new OrganizationAlreadyExistsException($input->slug);
                }

                $id = $organizations->save(new Organization(slug: $input->slug, name: $input->name));

                // Audit: a tenant lifecycle event is scoped to the affected tenant, so it
                // surfaces in that organization's own audit trail.
                $auditRecorder->record(new AuditEvent(
                    action: 'organization_created',
                    entityType: 'organization',
                    entityId: $id,
                    actorId: $input->actorUserId,
                    organizationId: $id,
                    occurredAt: $this->clock->now()->format('Y-m-d H:i:s'),
                    after: [
                        'organization_id' => $id,
                        'slug' => $input->slug,
                        'name' => $input->name,
                    ],
                ));

                return new CreateOrganizationOutput(id: $id, slug: $input->slug, name: $input->name);
            },
        );
    }
}
