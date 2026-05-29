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
        $this->query->execute(
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

        return $this->query->lastInsertId();
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
