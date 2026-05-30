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

    public function findByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM client_credits WHERE organization_id = ? ORDER BY id DESC LIMIT ? OFFSET ?',
            [$organizationId, $limit, $offset],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(int $organizationId): int
    {
        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM client_credits WHERE organization_id = ?', [$organizationId]);

        return (int) ($row['c'] ?? 0);
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
