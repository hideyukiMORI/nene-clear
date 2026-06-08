<?php

declare(strict_types=1);

namespace NeneClear\Tests\Reconciliation;

use NeneClear\Reconciliation\ClientCredit;
use NeneClear\Reconciliation\ClientCreditFilter;
use NeneClear\Reconciliation\ClientCreditRepositoryInterface;
use NeneClear\Reconciliation\ClientCreditStatus;

final class InMemoryClientCreditRepository implements ClientCreditRepositoryInterface
{
    /** @var array<int, ClientCredit> */
    private array $byId = [];

    private int $nextId = 1;

    public function save(ClientCredit $credit): int
    {
        $id = $credit->id ?? $this->nextId++;
        $this->byId[$id] = new ClientCredit(
            organizationId: $credit->organizationId,
            clientId: $credit->clientId,
            amountCents: $credit->amountCents,
            remainingCents: $credit->remainingCents,
            status: $credit->status,
            sourceBankTransactionId: $credit->sourceBankTransactionId,
            reconciliationId: $credit->reconciliationId,
            createdBy: $credit->createdBy,
            createdAt: $credit->createdAt,
            id: $id,
            source: $credit->source,
            manualReceivableId: $credit->manualReceivableId,
            clientName: $credit->clientName,
        );

        return $id;
    }

    public function findById(int $organizationId, int $id): ?ClientCredit
    {
        $credit = $this->byId[$id] ?? null;

        return ($credit !== null && $credit->organizationId === $organizationId) ? $credit : null;
    }

    public function applyAmount(int $organizationId, int $id, int $amountCents): ClientCredit
    {
        $credit = $this->byId[$id] ?? throw new \RuntimeException("Client credit $id not found.");
        $newRemaining = $credit->remainingCents - $amountCents;
        $newStatus = $newRemaining <= 0 ? ClientCreditStatus::Voided : $credit->status;

        $this->byId[$id] = new ClientCredit(
            organizationId: $credit->organizationId,
            clientId: $credit->clientId,
            amountCents: $credit->amountCents,
            remainingCents: max(0, $newRemaining),
            status: $newStatus,
            sourceBankTransactionId: $credit->sourceBankTransactionId,
            reconciliationId: $credit->reconciliationId,
            createdBy: $credit->createdBy,
            createdAt: $credit->createdAt,
            id: $credit->id,
            source: $credit->source,
            manualReceivableId: $credit->manualReceivableId,
            clientName: $credit->clientName,
        );

        return $this->byId[$id];
    }

    public function findByReconciliation(int $organizationId, int $reconciliationId): ?ClientCredit
    {
        foreach ($this->byId as $credit) {
            if ($credit->reconciliationId === $reconciliationId && $credit->organizationId === $organizationId) {
                return $credit;
            }
        }

        return null;
    }

    public function voidByReconciliation(int $reconciliationId): void
    {
        foreach ($this->byId as $id => $credit) {
            if ($credit->reconciliationId === $reconciliationId && $credit->status === ClientCreditStatus::Open) {
                $this->byId[$id] = new ClientCredit(
                    organizationId: $credit->organizationId,
                    clientId: $credit->clientId,
                    amountCents: $credit->amountCents,
                    remainingCents: $credit->remainingCents,
                    status: ClientCreditStatus::Voided,
                    sourceBankTransactionId: $credit->sourceBankTransactionId,
                    reconciliationId: $credit->reconciliationId,
                    createdBy: $credit->createdBy,
                    createdAt: $credit->createdAt,
                    id: $credit->id,
                    source: $credit->source,
                    manualReceivableId: $credit->manualReceivableId,
                    clientName: $credit->clientName,
                );
            }
        }
    }

    public function findByOrganization(int $organizationId, ClientCreditFilter $filter, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            fn (ClientCredit $c): bool => $this->matches($c, $organizationId, $filter),
        ));

        $asc = strtolower($filter->sortDirection) === 'asc';
        usort($matches, function (ClientCredit $a, ClientCredit $b) use ($filter, $asc): int {
            $r = $this->sortKey($a, $filter->sortColumn) <=> $this->sortKey($b, $filter->sortColumn);
            $r = $asc ? $r : -$r;
            return $r !== 0 ? $r : (($b->id ?? 0) <=> ($a->id ?? 0));
        });

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId, ClientCreditFilter $filter): int
    {
        return count(array_filter(
            $this->byId,
            fn (ClientCredit $c): bool => $this->matches($c, $organizationId, $filter),
        ));
    }

    private function matches(ClientCredit $c, int $organizationId, ClientCreditFilter $f): bool
    {
        if ($c->organizationId !== $organizationId) {
            return false;
        }
        if ($f->clientId !== null && $c->clientId !== $f->clientId) {
            return false;
        }
        if ($f->status !== null && $c->status !== $f->status) {
            return false;
        }
        if ($f->amountMinCents !== null && $c->amountCents < $f->amountMinCents) {
            return false;
        }
        if ($f->amountMaxCents !== null && $c->amountCents > $f->amountMaxCents) {
            return false;
        }
        if ($f->remainingMinCents !== null && $c->remainingCents < $f->remainingMinCents) {
            return false;
        }
        if ($f->remainingMaxCents !== null && $c->remainingCents > $f->remainingMaxCents) {
            return false;
        }
        $date = substr($c->createdAt, 0, 10);
        if ($f->createdFrom !== null && $date < $f->createdFrom) {
            return false;
        }
        if ($f->createdTo !== null && $date > $f->createdTo) {
            return false;
        }

        return true;
    }

    private function sortKey(ClientCredit $c, string $column): int|string
    {
        return match ($column) {
            'client_id' => $c->clientId ?? 0,
            'amount_cents' => $c->amountCents,
            'remaining_cents' => $c->remainingCents,
            'created_at' => $c->createdAt,
            default => $c->id ?? 0,
        };
    }

    /** @return list<ClientCredit> */
    public function all(): array
    {
        return array_values($this->byId);
    }
}
