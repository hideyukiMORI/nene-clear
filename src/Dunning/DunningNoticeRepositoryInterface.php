<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

interface DunningNoticeRepositoryInterface
{
    public function save(DunningNotice $notice): int;

    public function findById(int $organizationId, int $id): ?DunningNotice;

    /**
     * @return list<DunningNotice>
     */
    public function findByOrganization(int $organizationId, DunningNoticeFilter $filter, int $limit, int $offset): array;

    public function countByOrganization(int $organizationId, DunningNoticeFilter $filter): int;

    public function findLastByInvoice(int $organizationId, int $invoiceId): ?DunningNotice;
}
