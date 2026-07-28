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
            'SELECT organization_id, upstream_base_url, upstream_token_ref, dunning_min_interval_days, fiscal_year_end_month, '
            . 'is_dunning_schedule_enabled, dunning_initial_after_days, dunning_reminder_after_days, dunning_final_after_days, '
            . 'dunning_window_start_hour, dunning_window_end_hour, is_dunning_weekdays_only, dunning_max_per_run '
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
            dunningSchedule: new DunningSchedule(
                isEnabled: (bool) $row['is_dunning_schedule_enabled'],
                initialAfterDays: (int) $row['dunning_initial_after_days'],
                reminderAfterDays: (int) $row['dunning_reminder_after_days'],
                finalAfterDays: (int) $row['dunning_final_after_days'],
                windowStartHour: (int) $row['dunning_window_start_hour'],
                windowEndHour: (int) $row['dunning_window_end_hour'],
                isWeekdaysOnly: (bool) $row['is_dunning_weekdays_only'],
                maxPerRun: (int) $row['dunning_max_per_run'],
            ),
        );
    }

    public function fiscalYearEndMonth(int $organizationId): ?int
    {
        $row = $this->query->fetchOne(
            'SELECT fiscal_year_end_month FROM clear_settings WHERE organization_id = ?',
            [$organizationId],
        );

        return $row !== null && isset($row['fiscal_year_end_month']) ? (int) $row['fiscal_year_end_month'] : null;
    }

    /**
     * Deliberately does NOT persist {@see ClearSettings::$dunningSchedule} (#400).
     * `PUT /admin/clear-settings` is a full replace (#284) and its request body
     * cannot carry the schedule columns yet, so writing them here would let a
     * save of *unrelated* settings reset an enabled schedule back to the column
     * defaults — silently, because the operator only edited a bank account. The
     * columns keep their stored values until the settings API and UI learn about
     * them (design §11 step 4), which is also when this method starts writing them.
     */
    public function save(ClearSettings $settings): void
    {
        // Upsert without a destructive DELETE. Decide insert-vs-update by an
        // explicit existence check — NOT by the UPDATE's affected-row count.
        // MySQL (without MYSQL_ATTR_FOUND_ROWS) reports rows *changed*, not
        // matched, so a no-op save (identical values) reports 0 affected rows;
        // treating that as "row absent" fires the INSERT and collides with the
        // organization_id primary key → 500. SQLite counts the matched row as
        // changed, which is why this only surfaced on MySQL in production (#314).
        $exists = $this->query->fetchOne(
            'SELECT 1 AS present FROM clear_settings WHERE organization_id = ?',
            [$settings->organizationId],
        ) !== null;

        if ($exists) {
            $this->query->execute(
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

            return;
        }

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
