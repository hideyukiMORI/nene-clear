<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoClientCreditRepository implements ClientCreditRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, client_id, amount_cents, remaining_cents, status, '
        . 'source_bank_transaction_id, reconciliation_id, created_by, created_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function save(ClientCredit $credit): int
    {
        $this->query->execute(
            'INSERT INTO client_credits (organization_id, client_id, amount_cents, remaining_cents, status, '
            . 'source_bank_transaction_id, reconciliation_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $credit->organizationId,
                $credit->clientId,
                $credit->amountCents,
                $credit->remainingCents,
                $credit->status->value,
                $credit->sourceBankTransactionId,
                $credit->reconciliationId,
                $credit->createdBy,
                $credit->createdAt,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function findById(int $organizationId, int $id): ?ClientCredit
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM client_credits WHERE id = ? AND organization_id = ?',
            [$id, $organizationId],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function applyAmount(int $organizationId, int $id, int $amountCents): ClientCredit
    {
        $this->query->execute(
            'UPDATE client_credits SET remaining_cents = remaining_cents - ? WHERE id = ? AND organization_id = ?',
            [$amountCents, $id, $organizationId],
        );
        $this->query->execute(
            'UPDATE client_credits SET status = ? WHERE id = ? AND organization_id = ? AND remaining_cents <= 0 AND status = ?',
            [ClientCreditStatus::Voided->value, $id, $organizationId, ClientCreditStatus::Open->value],
        );

        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM client_credits WHERE id = ? AND organization_id = ?',
            [$id, $organizationId],
        );

        if ($row === null) {
            throw new \RuntimeException("Client credit $id not found after applyAmount.");
        }

        return $this->hydrate($row);
    }

    public function findByReconciliation(int $organizationId, int $reconciliationId): ?ClientCredit
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM client_credits WHERE reconciliation_id = ? AND organization_id = ? LIMIT 1',
            [$reconciliationId, $organizationId],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function voidByReconciliation(int $reconciliationId): void
    {
        $this->query->execute(
            'UPDATE client_credits SET status = ? WHERE reconciliation_id = ? AND status = ?',
            [ClientCreditStatus::Voided->value, $reconciliationId, ClientCreditStatus::Open->value],
        );
    }

    public function findByOrganization(int $organizationId, ClientCreditFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM client_credits WHERE ' . $where
            . ' ORDER BY ' . self::orderBy($filter) . ' LIMIT ? OFFSET ?',
            $params,
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(int $organizationId, ClientCreditFilter $filter): int
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM client_credits WHERE ' . $where, $params);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private function whereClause(int $organizationId, ClientCreditFilter $filter): array
    {
        $clauses = ['organization_id = ?'];
        /** @var list<string|int> $params */
        $params = [$organizationId];

        if ($filter->clientId !== null) {
            $clauses[] = 'client_id = ?';
            $params[] = $filter->clientId;
        }
        if ($filter->status !== null) {
            $clauses[] = 'status = ?';
            $params[] = $filter->status->value;
        }
        if ($filter->amountMinCents !== null) {
            $clauses[] = 'amount_cents >= ?';
            $params[] = $filter->amountMinCents;
        }
        if ($filter->amountMaxCents !== null) {
            $clauses[] = 'amount_cents <= ?';
            $params[] = $filter->amountMaxCents;
        }
        if ($filter->remainingMinCents !== null) {
            $clauses[] = 'remaining_cents >= ?';
            $params[] = $filter->remainingMinCents;
        }
        if ($filter->remainingMaxCents !== null) {
            $clauses[] = 'remaining_cents <= ?';
            $params[] = $filter->remainingMaxCents;
        }
        if ($filter->createdFrom !== null) {
            $clauses[] = 'DATE(created_at) >= ?';
            $params[] = $filter->createdFrom;
        }
        if ($filter->createdTo !== null) {
            $clauses[] = 'DATE(created_at) <= ?';
            $params[] = $filter->createdTo;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Whitelisted ORDER BY — column and direction are mapped from a closed set,
     * so user input is never interpolated into SQL.
     */
    private static function orderBy(ClientCreditFilter $filter): string
    {
        $column = match ($filter->sortColumn) {
            'client_id' => 'client_id',
            'amount_cents' => 'amount_cents',
            'remaining_cents' => 'remaining_cents',
            'created_at' => 'created_at',
            default => 'id',
        };
        $direction = strtolower($filter->sortDirection) === 'asc' ? 'ASC' : 'DESC';

        return $column . ' ' . $direction . ', id DESC';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ClientCredit
    {
        return new ClientCredit(
            organizationId: (int) $row['organization_id'],
            clientId: (int) $row['client_id'],
            amountCents: (int) $row['amount_cents'],
            remainingCents: (int) $row['remaining_cents'],
            status: ClientCreditStatus::from((string) $row['status']),
            sourceBankTransactionId: (int) $row['source_bank_transaction_id'],
            reconciliationId: (int) $row['reconciliation_id'],
            createdBy: (int) $row['created_by'],
            createdAt: (string) $row['created_at'],
            id: (int) $row['id'],
        );
    }
}
