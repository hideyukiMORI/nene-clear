<?php

declare(strict_types=1);

namespace NeneClear\Audit;

/**
 * Product-owned read side of the audit trail.
 *
 * The write side is the framework recorder (`Nene2\Audit`, ADR 0014); reads stay
 * in the product because they carry two concerns the framework read contract
 * intentionally leaves out: tenant (organization) scoping and Clear's own sort
 * whitelist (which includes `actor_user_id`, a column `Nene2\Audit\AuditQuery`
 * does not model).
 *
 * Rows are read from `audit_events` verbatim (raw `payload_json`) and normalized
 * so a framework-folded row (`{before, after, metadata:{…}}`) and a legacy flat
 * row (`{…context, before, after}`) yield the same API payload shape.
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
