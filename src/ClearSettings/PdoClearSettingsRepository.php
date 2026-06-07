<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneClear\BankImport\BankAccountRepositoryInterface;

final readonly class PdoClearSettingsRepository implements ClearSettingsRepositoryInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private BankAccountRepositoryInterface $bankAccounts,
    ) {
    }

    public function findByOrganization(int $organizationId): ?ClearSettings
    {
        $row = $this->query->fetchOne(
            'SELECT organization_id, upstream_base_url, upstream_token_ref, dunning_min_interval_days, fiscal_year_end_month '
            . 'FROM clear_settings WHERE organization_id = ?',
            [$organizationId],
        );

        if ($row === null) {
            return null;
        }

        return new ClearSettings(
            organizationId: (int) $row['organization_id'],
            upstreamBaseUrl: (string) $row['upstream_base_url'],
            upstreamTokenRef: (string) $row['upstream_token_ref'],
            dunningMinIntervalDays: (int) $row['dunning_min_interval_days'],
            fiscalYearEndMonth: isset($row['fiscal_year_end_month']) ? (int) $row['fiscal_year_end_month'] : null,
            bankAccounts: $this->bankAccounts->findByOrganization($organizationId),
        );
    }

    public function save(ClearSettings $settings): void
    {
        // Upsert without a destructive DELETE: update the existing row, and
        // insert only when no row was affected (first save for the org).
        $affected = $this->query->execute(
            'UPDATE clear_settings SET upstream_base_url = ?, upstream_token_ref = ?, dunning_min_interval_days = ?, fiscal_year_end_month = ? '
            . 'WHERE organization_id = ?',
            [
                $settings->upstreamBaseUrl,
                $settings->upstreamTokenRef,
                $settings->dunningMinIntervalDays,
                $settings->fiscalYearEndMonth,
                $settings->organizationId,
            ],
        );

        if ($affected === 0) {
            $this->query->execute(
                'INSERT INTO clear_settings (organization_id, upstream_base_url, upstream_token_ref, dunning_min_interval_days, fiscal_year_end_month) '
                . 'VALUES (?, ?, ?, ?, ?)',
                [
                    $settings->organizationId,
                    $settings->upstreamBaseUrl,
                    $settings->upstreamTokenRef,
                    $settings->dunningMinIntervalDays,
                    $settings->fiscalYearEndMonth,
                ],
            );
        }
    }
}
