<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoReconciliationRepository implements ReconciliationRepositoryInterface
{
    private const string RECON_COLUMNS = 'id, organization_id, bank_transaction_id, status, reason_code, '
        . 'confirmed_by, confirmed_at, reversed_at, reversal_reason';

    private const string ALLOC_COLUMNS = 'id, organization_id, payment_reconciliation_id, invoice_id, '
        . 'amount_cents, payment_id, external_reference';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $organizationId, int $id): ?Reconciliation
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::RECON_COLUMNS . ' FROM payment_reconciliations WHERE id = ? AND organization_id = ?',
            [$id, $organizationId],
        );

        return $row !== null ? $this->hydrateReconciliation($row) : null;
    }

    public function findByOrganization(int $organizationId, ?ReconciliationStatus $status, int $limit, int $offset): array
    {
        if ($status === null) {
            $rows = $this->query->fetchAll(
                'SELECT ' . self::RECON_COLUMNS . ' FROM payment_reconciliations WHERE organization_id = ? '
                . 'ORDER BY id DESC LIMIT ? OFFSET ?',
                [$organizationId, $limit, $offset],
            );
        } else {
            $rows = $this->query->fetchAll(
                'SELECT ' . self::RECON_COLUMNS . ' FROM payment_reconciliations WHERE organization_id = ? AND status = ? '
                . 'ORDER BY id DESC LIMIT ? OFFSET ?',
                [$organizationId, $status->value, $limit, $offset],
            );
        }

        return array_map($this->hydrateReconciliation(...), $rows);
    }

    public function countByOrganization(int $organizationId, ?ReconciliationStatus $status): int
    {
        $row = $status === null
            ? $this->query->fetchOne('SELECT COUNT(*) AS c FROM payment_reconciliations WHERE organization_id = ?', [$organizationId])
            : $this->query->fetchOne('SELECT COUNT(*) AS c FROM payment_reconciliations WHERE organization_id = ? AND status = ?', [$organizationId, $status->value]);

        return (int) ($row['c'] ?? 0);
    }

    public function save(Reconciliation $reconciliation): int
    {
        $this->query->execute(
            'INSERT INTO payment_reconciliations (organization_id, bank_transaction_id, status, reason_code, confirmed_by, confirmed_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
            [
                $reconciliation->organizationId,
                $reconciliation->bankTransactionId,
                $reconciliation->status->value,
                $reconciliation->reasonCode,
                $reconciliation->confirmedBy,
                $reconciliation->confirmedAt,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function saveAllocation(ReconciliationAllocation $allocation): int
    {
        $this->query->execute(
            'INSERT INTO reconciliation_allocations (organization_id, payment_reconciliation_id, invoice_id, amount_cents, payment_id, external_reference) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
            [
                $allocation->organizationId,
                $allocation->reconciliationId,
                $allocation->invoiceId,
                $allocation->amountCents,
                $allocation->paymentId,
                $allocation->externalReference,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function findAllocationsByReconciliation(int $organizationId, int $reconciliationId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::ALLOC_COLUMNS . ' FROM reconciliation_allocations WHERE payment_reconciliation_id = ? AND organization_id = ? ORDER BY id ASC',
            [$reconciliationId, $organizationId],
        );

        return array_map($this->hydrateAllocation(...), $rows);
    }

    public function reverseById(int $id, string $reversedAt, string $reversalReason): void
    {
        $this->query->execute(
            'UPDATE payment_reconciliations SET status = ?, reversed_at = ?, reversal_reason = ? WHERE id = ?',
            [ReconciliationStatus::Reversed->value, $reversedAt, $reversalReason, $id],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateReconciliation(array $row): Reconciliation
    {
        return new Reconciliation(
            organizationId: (int) $row['organization_id'],
            bankTransactionId: (int) $row['bank_transaction_id'],
            status: ReconciliationStatus::from((string) $row['status']),
            confirmedBy: (int) $row['confirmed_by'],
            confirmedAt: (string) $row['confirmed_at'],
            reasonCode: isset($row['reason_code']) ? (string) $row['reason_code'] : null,
            reversedAt: isset($row['reversed_at']) ? (string) $row['reversed_at'] : null,
            reversalReason: isset($row['reversal_reason']) ? (string) $row['reversal_reason'] : null,
            id: (int) $row['id'],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateAllocation(array $row): ReconciliationAllocation
    {
        return new ReconciliationAllocation(
            organizationId: (int) $row['organization_id'],
            reconciliationId: (int) $row['payment_reconciliation_id'],
            invoiceId: (int) $row['invoice_id'],
            amountCents: (int) $row['amount_cents'],
            paymentId: isset($row['payment_id']) ? (int) $row['payment_id'] : null,
            externalReference: isset($row['external_reference']) ? (string) $row['external_reference'] : null,
            id: (int) $row['id'],
        );
    }
}
