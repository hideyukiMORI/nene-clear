<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoDunningNoticeRepository implements DunningNoticeRepositoryInterface
{
    private const string COLUMNS = 'id, organization_id, invoice_id, invoice_number, client_id, '
        . 'recipient_email, outstanding_cents, due_at, channel, template_version, sent_by, sent_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function save(DunningNotice $notice): int
    {
        $this->query->execute(
            'INSERT INTO dunning_notices (organization_id, invoice_id, invoice_number, client_id, '
            . 'recipient_email, outstanding_cents, due_at, channel, template_version, sent_by, sent_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $notice->organizationId,
                $notice->invoiceId,
                $notice->invoiceNumber,
                $notice->clientId,
                $notice->recipientEmail,
                $notice->outstandingCents,
                $notice->dueAt,
                $notice->channel,
                $notice->templateVersion,
                $notice->sentBy,
                $notice->sentAt,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function findById(int $organizationId, int $id): ?DunningNotice
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM dunning_notices WHERE id = ? AND organization_id = ?',
            [$id, $organizationId],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByOrganization(int $organizationId, DunningNoticeFilter $filter, int $limit, int $offset): array
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM dunning_notices WHERE ' . $where
            . ' ORDER BY ' . self::orderBy($filter) . ' LIMIT ? OFFSET ?',
            $params,
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countByOrganization(int $organizationId, DunningNoticeFilter $filter): int
    {
        [$where, $params] = $this->whereClause($organizationId, $filter);
        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM dunning_notices WHERE ' . $where, $params);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private function whereClause(int $organizationId, DunningNoticeFilter $filter): array
    {
        $clauses = ['organization_id = ?'];
        /** @var list<string|int> $params */
        $params = [$organizationId];

        if ($filter->invoiceNumber !== null) {
            $clauses[] = 'invoice_number LIKE ?';
            $params[] = '%' . $filter->invoiceNumber . '%';
        }
        if ($filter->recipientEmail !== null) {
            $clauses[] = 'recipient_email LIKE ?';
            $params[] = '%' . $filter->recipientEmail . '%';
        }
        if ($filter->outstandingMinCents !== null) {
            $clauses[] = 'outstanding_cents >= ?';
            $params[] = $filter->outstandingMinCents;
        }
        if ($filter->outstandingMaxCents !== null) {
            $clauses[] = 'outstanding_cents <= ?';
            $params[] = $filter->outstandingMaxCents;
        }
        if ($filter->sentFrom !== null) {
            $clauses[] = 'DATE(sent_at) >= ?';
            $params[] = $filter->sentFrom;
        }
        if ($filter->sentTo !== null) {
            $clauses[] = 'DATE(sent_at) <= ?';
            $params[] = $filter->sentTo;
        }
        if ($filter->sentBy !== null) {
            $clauses[] = 'sent_by = ?';
            $params[] = $filter->sentBy;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /** Whitelisted ORDER BY — column/direction mapped from a closed set. */
    private static function orderBy(DunningNoticeFilter $filter): string
    {
        $column = match ($filter->sortColumn) {
            'invoice_number' => 'invoice_number',
            'recipient_email' => 'recipient_email',
            'outstanding_cents' => 'outstanding_cents',
            'sent_by' => 'sent_by',
            default => 'sent_at',
        };
        $direction = strtolower($filter->sortDirection) === 'asc' ? 'ASC' : 'DESC';

        return $column . ' ' . $direction . ', id DESC';
    }

    public function findLastByInvoice(int $organizationId, int $invoiceId): ?DunningNotice
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM dunning_notices WHERE organization_id = ? AND invoice_id = ? '
            . 'ORDER BY id DESC LIMIT 1',
            [$organizationId, $invoiceId],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): DunningNotice
    {
        return new DunningNotice(
            organizationId: (int) $row['organization_id'],
            invoiceId: (int) $row['invoice_id'],
            invoiceNumber: (string) $row['invoice_number'],
            clientId: (int) $row['client_id'],
            recipientEmail: (string) $row['recipient_email'],
            outstandingCents: (int) $row['outstanding_cents'],
            dueAt: (string) $row['due_at'],
            channel: (string) $row['channel'],
            templateVersion: (string) $row['template_version'],
            sentBy: (int) $row['sent_by'],
            sentAt: (string) $row['sent_at'],
            id: (int) $row['id'],
        );
    }
}
