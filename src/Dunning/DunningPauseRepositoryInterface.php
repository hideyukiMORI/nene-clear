<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

interface DunningPauseRepositoryInterface
{
    public function save(DunningPause $pause): int;

    public function findActiveByInvoice(int $organizationId, int $invoiceId): ?DunningPause;

    public function resumeByInvoice(int $organizationId, int $invoiceId, int $unpausedBy, string $unpausedAt): void;

    /**
     * @return list<DunningPause>
     */
    public function findByOrganization(int $organizationId, bool $activeOnly, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId, bool $activeOnly): int;
}
