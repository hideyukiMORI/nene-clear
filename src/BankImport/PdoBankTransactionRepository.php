<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoBankTransactionRepository implements BankTransactionRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, bank_import_batch_id, bank_account_id, value_date, '
        . 'amount_cents, counterparty_text, line_key, status';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function save(BankTransaction $transaction): int
    {
        return $this->query->insert(
            'INSERT INTO bank_transactions (organization_id, bank_import_batch_id, bank_account_id, value_date, '
            . 'amount_cents, counterparty_text, line_key, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $transaction->organizationId,
                $transaction->bankImportBatchId,
                $transaction->bankAccountId,
                $transaction->valueDate,
                $transaction->amountCents,
                $transaction->counterpartyText,
                $transaction->lineKey,
                $transaction->status->value,
            ],
        );
    }

    public function findById(int $organizationId, int $id): ?BankTransaction
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM bank_transactions WHERE id = ? AND organization_id = ?',
            [$id, $organizationId],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function countByBatch(int $bankImportBatchId): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS c FROM bank_transactions WHERE bank_import_batch_id = ?',
            [$bankImportBatchId],
        );

        if ($row === null) {
            return 0;
        }

        return (int) ($row['c'] ?? 0);
    }

    public function findByBatch(int $bankImportBatchId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM bank_transactions WHERE bank_import_batch_id = ? ORDER BY id ASC',
            [$bankImportBatchId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function findByOrganization(int $organizationId, BankTransactionFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($organizationId, $filter);
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM bank_transactions WHERE ' . $where
            . ' ORDER BY ' . self::orderBy($filter) . ' LIMIT ? OFFSET ?',
            [...$params, $limit, $offset],
        );

        return array_map($this->hydrate(...), $rows);
    }

    /** Whitelisted ORDER BY — column/direction mapped from a closed set. */
    private static function orderBy(BankTransactionFilter $filter): string
    {
        $column = match ($filter->sortColumn) {
            'amount_cents' => 'amount_cents',
            'counterparty_text' => 'counterparty_text',
            'status' => 'status',
            default => 'value_date',
        };
        $direction = strtolower($filter->sortDirection) === 'asc' ? 'ASC' : 'DESC';

        return $column . ' ' . $direction . ', id DESC';
    }

    public function countByOrganization(int $organizationId, BankTransactionFilter $filter): int
    {
        [$where, $params] = $this->buildWhere($organizationId, $filter);
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS c FROM bank_transactions WHERE ' . $where,
            $params,
        );

        return (int) ($row['c'] ?? 0);
    }

    public function findUnmatchedByOrganization(int $organizationId, BankTransactionFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($organizationId, $filter);
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM bank_transactions WHERE ' . $where
            . ' ORDER BY ' . self::orderBy($filter) . ' LIMIT ? OFFSET ?',
            [...$params, $limit, $offset],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countUnmatchedByOrganization(int $organizationId, BankTransactionFilter $filter): int
    {
        [$where, $params] = $this->buildWhere($organizationId, $filter);
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS c FROM bank_transactions WHERE ' . $where,
            $params,
        );

        return (int) ($row['c'] ?? 0);
    }

    public function updateStatusById(int $organizationId, int $id, BankTransactionStatus $status): void
    {
        $this->query->execute(
            'UPDATE bank_transactions SET status = ? WHERE id = ? AND organization_id = ?',
            [$status->value, $id, $organizationId],
        );
    }

    public function voidByBatchId(int $bankImportBatchId): void
    {
        $this->query->execute(
            'UPDATE bank_transactions SET status = ? WHERE bank_import_batch_id = ? AND status = ?',
            [BankTransactionStatus::Voided->value, $bankImportBatchId, BankTransactionStatus::Unmatched->value],
        );
    }

    /**
     * @return array{string, list<int|string>}
     */
    private function buildWhere(int $organizationId, BankTransactionFilter $filter): array
    {
        $wheres = ['organization_id = ?'];
        $params = [$organizationId];

        if ($filter->openForMatchingOnly) {
            $wheres[] = 'status IN (?, ?)';
            $params[] = BankTransactionStatus::Unmatched->value;
            $params[] = BankTransactionStatus::PartiallyMatched->value;
        } elseif ($filter->status !== null) {
            $wheres[] = 'status = ?';
            $params[] = $filter->status->value;
        }
        if ($filter->valueDateFrom !== null) {
            $wheres[] = 'value_date >= ?';
            $params[] = $filter->valueDateFrom;
        }
        if ($filter->valueDateTo !== null) {
            $wheres[] = 'value_date <= ?';
            $params[] = $filter->valueDateTo;
        }
        if ($filter->amountMinCents !== null) {
            $wheres[] = 'amount_cents >= ?';
            $params[] = $filter->amountMinCents;
        }
        if ($filter->amountMaxCents !== null) {
            $wheres[] = 'amount_cents <= ?';
            $params[] = $filter->amountMaxCents;
        }
        if ($filter->counterparty !== null && $filter->counterparty !== '') {
            $wheres[] = 'LOWER(counterparty_text) LIKE ?';
            $params[] = '%' . strtolower($filter->counterparty) . '%';
        }

        return [implode(' AND ', $wheres), $params];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): BankTransaction
    {
        return new BankTransaction(
            organizationId: (int) $row['organization_id'],
            bankImportBatchId: (int) $row['bank_import_batch_id'],
            bankAccountId: (int) $row['bank_account_id'],
            valueDate: (string) $row['value_date'],
            amountCents: (int) $row['amount_cents'],
            counterpartyText: (string) $row['counterparty_text'],
            lineKey: (string) $row['line_key'],
            status: BankTransactionStatus::from((string) $row['status']),
            id: (int) $row['id'],
        );
    }
}
