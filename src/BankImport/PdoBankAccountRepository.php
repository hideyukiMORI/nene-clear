<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneClear\Security\Encryptor;

final readonly class PdoBankAccountRepository implements BankAccountRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, bank_name, bank_branch, account_type, account_number, '
        . 'csv_encoding, csv_date_format, csv_date_column, csv_amount_column, csv_counterparty_column, csv_header_rows';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private ?Encryptor $encryptor = null,
    ) {
    }

    public function findById(int $id): ?BankAccount
    {
        $row = $this->query->fetchOne('SELECT ' . self::COLUMNS . ' FROM bank_accounts WHERE id = ? AND is_deleted = FALSE', [$id]);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByOrganization(int $organizationId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM bank_accounts WHERE organization_id = ? AND is_deleted = FALSE ORDER BY id ASC',
            [$organizationId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function deleteByOrganization(int $organizationId, string $deletedAt): void
    {
        // Soft delete — bank accounts are referenced by import history and are
        // never hard-deleted. `$deletedAt` comes from the use case's clock.
        $this->query->execute(
            'UPDATE bank_accounts SET is_deleted = TRUE, deleted_at = ? WHERE organization_id = ? AND is_deleted = FALSE',
            [$deletedAt, $organizationId],
        );
    }

    public function save(BankAccount $account): int
    {
        return $this->query->insert(
            'INSERT INTO bank_accounts (organization_id, bank_name, bank_branch, account_type, account_number, '
            . 'csv_encoding, csv_date_format, csv_date_column, csv_amount_column, csv_counterparty_column, csv_header_rows) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $account->organizationId,
                $account->bankName,
                $account->bankBranch,
                $account->accountType->value,
                $this->encryptor?->encrypt($account->accountNumber) ?? $account->accountNumber,
                $account->csvEncoding,
                $account->csvDateFormat,
                $account->csvDateColumn,
                $account->csvAmountColumn,
                $account->csvCounterpartyColumn,
                $account->csvHeaderRows,
            ],
        );
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
            accountNumber: $this->encryptor?->decrypt((string) $row['account_number']) ?? (string) $row['account_number'],
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
