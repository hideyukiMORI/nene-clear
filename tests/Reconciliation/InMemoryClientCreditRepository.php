<?php

declare(strict_types=1);

namespace NeneClear\Tests\Reconciliation;

use NeneClear\Reconciliation\ClientCredit;
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
                );
            }
        }
    }

    public function findByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (ClientCredit $c): bool => $c->organizationId === $organizationId,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId): int
    {
        return count(array_filter(
            $this->byId,
            static fn (ClientCredit $c): bool => $c->organizationId === $organizationId,
        ));
    }

    /** @return list<ClientCredit> */
    public function all(): array
    {
        return array_values($this->byId);
    }
}
