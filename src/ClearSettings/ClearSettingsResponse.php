<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

final readonly class ClearSettingsResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ClearSettings $settings): array
    {
        return [
            'organization_id' => $settings->organizationId,
            'upstream_base_url' => $settings->upstreamBaseUrl,
            'upstream_token_ref' => $settings->upstreamTokenRef,
            'dunning_min_interval_days' => $settings->dunningMinIntervalDays,
            'fiscal_year_end_month' => $settings->fiscalYearEndMonth,
            'bank_accounts' => array_map(
                static fn ($account): array => [
                    'bank_account_id' => $account->id,
                    'bank_name' => $account->bankName,
                    'bank_branch' => $account->bankBranch,
                    'account_type' => $account->accountType->value,
                    'account_number' => $account->accountNumber,
                    'csv_encoding' => $account->csvEncoding,
                    'csv_date_format' => $account->csvDateFormat,
                    'csv_date_column' => $account->csvDateColumn,
                    'csv_amount_column' => $account->csvAmountColumn,
                    'csv_counterparty_column' => $account->csvCounterpartyColumn,
                    'csv_header_rows' => $account->csvHeaderRows,
                ],
                $settings->bankAccounts,
            ),
        ];
    }
}
