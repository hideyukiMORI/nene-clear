<?php

declare(strict_types=1);

namespace NeneClear\Audit;

/**
 * Product-owned read side of the audit trail.
 *
 * The write side is the framework recorder (`Nene2\Audit`, ADR 0014); reads stay
 * in the product because they carry concerns the framework read contract
 * intentionally leaves out: tenant (organization) scoping, Clear's own sort
 * whitelist (which includes `actor_id`, a column `Nene2\Audit\AuditQuery` does
 * not model), and inclusive `DATE(occurred_at)` range bounds.
 *
 * Rows are stored in the framework-canonical shape (stage 2, Issue #258):
 * `before_json` / `after_json` / `metadata_json` hydrate straight into the
 * product {@see AuditEvent} view.
 */
interface AuditReadRepositoryInterface
{
    /**
     * Tenant-scoped. Filtered + sorted via {@see AuditEventFilter}.
     *
     * @return list<AuditEvent>
     */
    public function findByOrganization(int $organizationId, AuditEventFilter $filter, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId, AuditEventFilter $filter): int;
}
