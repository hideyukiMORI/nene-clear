<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneClear\Receivable\ReceivableSource;

final readonly class PdoReconciliationRepository implements ReconciliationRepositoryInterface
{
    private const string RECON_COLUMNS = 'id, organization_id, bank_transaction_id, status, reason_code, '
        . 'confirmed_by, confirmed_at, reversed_at, reversal_reason, idempotency_key';

    private const string ALLOC_COLUMNS = 'id, organization_id, payment_reconciliation_id, invoice_id, '
        . 'amount_cents, payment_id, external_reference, source, manual_receivable_id';

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

    public function findByOrganization(int $organizationId, ReconciliationFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->query->fetchAll(
            'SELECT ' . self::RECON_COLUMNS . ' FROM payment_reconciliations WHERE ' . $where
            . ' ORDER BY ' . self::orderBy($filter) . ' LIMIT ? OFFSET ?',
            $params,
        );

        return array_map($this->hydrateReconciliation(...), $rows);
    }

    public function countByOrganization(int $organizationId, ReconciliationFilter $filter): int
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM payment_reconciliations WHERE ' . $where, $params);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private function whereClause(int $organizationId, ReconciliationFilter $filter): array
    {
        $clauses = ['organization_id = ?'];
        /** @var list<string|int> $params */
        $params = [$organizationId];

        if ($filter->status !== null) {
            $clauses[] = 'status = ?';
            $params[] = $filter->status->value;
        }
        if ($filter->bankTransactionId !== null) {
            $clauses[] = 'bank_transaction_id = ?';
            $params[] = $filter->bankTransactionId;
        }
        if ($filter->invoiceId !== null) {
            $clauses[] = 'EXISTS (SELECT 1 FROM reconciliation_allocations a '
                . 'WHERE a.payment_reconciliation_id = payment_reconciliations.id AND a.invoice_id = ?)';
            $params[] = $filter->invoiceId;
        }
        if ($filter->confirmedFrom !== null) {
            $clauses[] = 'DATE(confirmed_at) >= ?';
            $params[] = $filter->confirmedFrom;
        }
        if ($filter->confirmedTo !== null) {
            $clauses[] = 'DATE(confirmed_at) <= ?';
            $params[] = $filter->confirmedTo;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /** Whitelisted ORDER BY — column/direction mapped from a closed set. */
    private static function orderBy(ReconciliationFilter $filter): string
    {
        $column = match ($filter->sortColumn) {
            'bank_transaction_id' => 'bank_transaction_id',
            'status' => 'status',
            'confirmed_at' => 'confirmed_at',
            default => 'id',
        };
        $direction = strtolower($filter->sortDirection) === 'asc' ? 'ASC' : 'DESC';

        return $column . ' ' . $direction . ', id DESC';
    }

    public function findByIdempotencyKey(int $organizationId, string $key): ?Reconciliation
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::RECON_COLUMNS . ' FROM payment_reconciliations WHERE organization_id = ? AND idempotency_key = ?',
            [$organizationId, $key],
        );

        return $row !== null ? $this->hydrateReconciliation($row) : null;
    }

    public function save(Reconciliation $reconciliation): int
    {
        $this->query->execute(
            'INSERT INTO payment_reconciliations (organization_id, bank_transaction_id, status, reason_code, confirmed_by, confirmed_at, idempotency_key) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $reconciliation->organizationId,
                $reconciliation->bankTransactionId,
                $reconciliation->status->value,
                $reconciliation->reasonCode,
                $reconciliation->confirmedBy,
                $reconciliation->confirmedAt,
                $reconciliation->idempotencyKey,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function saveAllocation(ReconciliationAllocation $allocation): int
    {
        $this->query->execute(
            'INSERT INTO reconciliation_allocations (organization_id, payment_reconciliation_id, invoice_id, amount_cents, payment_id, external_reference, source, manual_receivable_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $allocation->organizationId,
                $allocation->reconciliationId,
                $allocation->invoiceId,
                $allocation->amountCents,
                $allocation->paymentId,
                $allocation->externalReference,
                $allocation->source->value,
                $allocation->manualReceivableId,
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
            idempotencyKey: isset($row['idempotency_key']) ? (string) $row['idempotency_key'] : null,
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
            invoiceId: isset($row['invoice_id']) ? (int) $row['invoice_id'] : null,
            amountCents: (int) $row['amount_cents'],
            paymentId: isset($row['payment_id']) ? (int) $row['payment_id'] : null,
            externalReference: isset($row['external_reference']) ? (string) $row['external_reference'] : null,
            id: (int) $row['id'],
            source: ReceivableSource::from((string) ($row['source'] ?? 'invoice_upstream')),
            manualReceivableId: isset($row['manual_receivable_id']) ? (int) $row['manual_receivable_id'] : null,
        );
    }
}
