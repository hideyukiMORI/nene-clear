<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizations
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $organizations,
        private Closure $auditRecorder,
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
                $auditRecorder = ($this->auditRecorder)($tx);

                $organization = $organizations->findById($id);

                if ($organization === null) {
                    throw new OrganizationNotFoundException($id);
                }

                $now = $this->clock->now()->format('Y-m-d H:i:s');
                $organizations->delete($id, $now);

                // Audit: deletion carries the prior state (`before` only), scoped to the
                // now-removed tenant.
                $auditRecorder->record(
                    $id,
                    $actorUserId,
                    $now,
                    'organization_deleted',
                    'organization',
                    $id,
                    [
                        'before' => [
                            'organization_id' => $id,
                            'slug' => $organization->slug,
                            'name' => $organization->name,
                        ],
                    ],
                );
            },
        );
    }
}
