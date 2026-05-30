<?php

declare(strict_types=1);

namespace NeneClear\Tests\Dunning;

use NeneClear\Dunning\DunningNotice;
use NeneClear\Dunning\DunningNoticeRepositoryInterface;

final class InMemoryDunningNoticeRepository implements DunningNoticeRepositoryInterface
{
    /** @var array<int, DunningNotice> */
    private array $byId = [];

    private int $nextId = 1;

    public function save(DunningNotice $notice): int
    {
        $id = $notice->id ?? $this->nextId++;
        $this->byId[$id] = new DunningNotice(
            organizationId: $notice->organizationId,
            invoiceId: $notice->invoiceId,
            invoiceNumber: $notice->invoiceNumber,
            clientId: $notice->clientId,
            recipientEmail: $notice->recipientEmail,
            outstandingCents: $notice->outstandingCents,
            dueAt: $notice->dueAt,
            channel: $notice->channel,
            sentBy: $notice->sentBy,
            sentAt: $notice->sentAt,
            id: $id,
        );

        return $id;
    }

    public function findById(int $organizationId, int $id): ?DunningNotice
    {
        $n = $this->byId[$id] ?? null;

        return ($n !== null && $n->organizationId === $organizationId) ? $n : null;
    }

    public function findByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (DunningNotice $n): bool => $n->organizationId === $organizationId,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId): int
    {
        return count(array_filter(
            $this->byId,
            static fn (DunningNotice $n): bool => $n->organizationId === $organizationId,
        ));
    }

    public function findLastByInvoice(int $organizationId, int $invoiceId): ?DunningNotice
    {
        $matches = array_filter(
            $this->byId,
            static fn (DunningNotice $n): bool => $n->organizationId === $organizationId && $n->invoiceId === $invoiceId,
        );

        if (empty($matches)) {
            return null;
        }

        return end($matches);
    }
}
