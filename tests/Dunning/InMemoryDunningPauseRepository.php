<?php

declare(strict_types=1);

namespace NeneClear\Tests\Dunning;

use NeneClear\Dunning\DunningPause;
use NeneClear\Dunning\DunningPauseRepositoryInterface;

final class InMemoryDunningPauseRepository implements DunningPauseRepositoryInterface
{
    /** @var array<int, DunningPause> */
    private array $byId = [];

    private int $nextId = 1;

    public function save(DunningPause $pause): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = new DunningPause(
            organizationId: $pause->organizationId,
            invoiceId: $pause->invoiceId,
            pausedBy: $pause->pausedBy,
            pausedAt: $pause->pausedAt,
            pausedReason: $pause->pausedReason,
            unpausedBy: $pause->unpausedBy,
            unpausedAt: $pause->unpausedAt,
            id: $id,
        );

        return $id;
    }

    public function findActiveByInvoice(int $organizationId, int $invoiceId): ?DunningPause
    {
        foreach (array_reverse($this->byId, true) as $pause) {
            if ($pause->organizationId === $organizationId
                && $pause->invoiceId === $invoiceId
                && $pause->unpausedAt === null) {
                return $pause;
            }
        }

        return null;
    }

    public function resumeByInvoice(int $organizationId, int $invoiceId, int $unpausedBy, string $unpausedAt): void
    {
        foreach ($this->byId as $id => $pause) {
            if ($pause->organizationId === $organizationId
                && $pause->invoiceId === $invoiceId
                && $pause->unpausedAt === null) {
                $this->byId[$id] = new DunningPause(
                    organizationId: $pause->organizationId,
                    invoiceId: $pause->invoiceId,
                    pausedBy: $pause->pausedBy,
                    pausedAt: $pause->pausedAt,
                    pausedReason: $pause->pausedReason,
                    unpausedBy: $unpausedBy,
                    unpausedAt: $unpausedAt,
                    id: $pause->id,
                );
            }
        }
    }

    public function findByOrganization(int $organizationId, bool $activeOnly, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (DunningPause $p): bool =>
                $p->organizationId === $organizationId
                && (!$activeOnly || $p->unpausedAt === null),
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId, bool $activeOnly): int
    {
        return count($this->findByOrganization($organizationId, $activeOnly, PHP_INT_MAX, 0));
    }
}
