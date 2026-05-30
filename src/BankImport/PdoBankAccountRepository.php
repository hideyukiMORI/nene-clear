<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoBankAccountRepository implements BankAccountRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, bank_name, bank_branch, account_type, account_number, '
        . 'csv_encoding, csv_date_format, csv_date_column, csv_amount_column, csv_counterparty_column, csv_header_rows';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $id): ?BankAccount
    {
        $row = $this->query->fetchOne('SELECT ' . self::COLUMNS . ' FROM bank_accounts WHERE id = ?', [$id]);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByOrganization(int $organizationId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM bank_accounts WHERE organization_id = ? ORDER BY id ASC',
            [$organizationId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function deleteByOrganization(int $organizationId): void
    {
        $this->query->execute('DELETE FROM bank_accounts WHERE organization_id = ?', [$organizationId]);
    }

    public function save(BankAccount $account): int
    {
        $this->query->execute(
            'INSERT INTO bank_accounts (organization_id, bank_name, bank_branch, account_type, account_number, '
            . 'csv_encoding, csv_date_format, csv_date_column, csv_amount_column, csv_counterparty_column, csv_header_rows) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $account->organizationId,
                $account->bankName,
                $account->bankBranch,
                $account->accountType->value,
                $account->accountNumber,
                $account->csvEncoding,
                $account->csvDateFormat,
                $account->csvDateColumn,
                $account->csvAmountColumn,
                $account->csvCounterpartyColumn,
                $account->csvHeaderRows,
            ],
        );

        return $this->query->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): BankAccount
    {
        return new BankAccount(
            organizationId: (int) $row['organization_id'],
            bankName: (string) $row['bank_name'],
            bankBranch: (string) $row['bank_branch'],
            accountType: AccountType::from((string) $row['account_type']),
            accountNumber: (string) $row['account_number'],
            csvEncoding: (string) $row['csv_encoding'],
            csvDateFormat: (string) $row['csv_date_format'],
            csvDateColumn: (int) $row['csv_date_column'],
            csvAmountColumn: (int) $row['csv_amount_column'],
            csvCounterpartyColumn: (int) $row['csv_counterparty_column'],
            csvHeaderRows: (int) $row['csv_header_rows'],
            id: (int) $row['id'],
        );
    }
}
