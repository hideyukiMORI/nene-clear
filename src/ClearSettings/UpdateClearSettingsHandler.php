<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UpdateClearSettingsHandler
{
    public function __construct(
        private UpdateClearSettingsUseCaseInterface $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $this->organization->id();
        $body = (array) $request->getParsedBody();
        $errors = [];

        $upstreamBaseUrl = is_string($body['upstream_base_url'] ?? null) ? trim($body['upstream_base_url']) : '';
        $upstreamTokenRef = is_string($body['upstream_token_ref'] ?? null) ? trim($body['upstream_token_ref']) : '';

        $dunningMinIntervalDaysRaw = $body['dunning_min_interval_days'] ?? 7;
        $dunningMinIntervalDays = is_numeric($dunningMinIntervalDaysRaw) ? (int) $dunningMinIntervalDaysRaw : 0;
        if ($dunningMinIntervalDays < 1) {
            $errors[] = new ValidationError('dunning_min_interval_days', 'dunning_min_interval_days must be at least 1.', 'invalid');
        }

        // Fiscal year-end month (決算月): optional; null/empty = unset. If present
        // it must be a calendar month 1–12.
        $fiscalRaw = $body['fiscal_year_end_month'] ?? null;
        $fiscalYearEndMonth = null;
        if ($fiscalRaw !== null && $fiscalRaw !== '') {
            if (is_numeric($fiscalRaw) && (int) $fiscalRaw >= 1 && (int) $fiscalRaw <= 12) {
                $fiscalYearEndMonth = (int) $fiscalRaw;
            } else {
                $errors[] = new ValidationError('fiscal_year_end_month', 'fiscal_year_end_month must be between 1 and 12.', 'invalid');
            }
        }

        // Scheduled dunning (#400 §6). Every key is optional and falls back to the
        // DunningSchedule default — but this endpoint is **full-replace** (#284):
        // omitting a key resets it to that default, it does not preserve whatever
        // is stored. A client that wants to change one field must send them all.
        $defaults = new DunningSchedule();

        $intSetting = static function (string $key, int $default, int $min, int $max) use ($body, &$errors): int {
            $raw = $body[$key] ?? $default;

            if (!is_numeric($raw)) {
                $errors[] = new ValidationError($key, $key . ' must be a number.', 'invalid');

                return $default;
            }

            $value = (int) $raw;

            if ($value < $min || $value > $max) {
                $errors[] = new ValidationError($key, sprintf('%s must be between %d and %d.', $key, $min, $max), 'invalid');

                return $default;
            }

            return $value;
        };

        $boolSetting = static fn (string $key, bool $default): bool => isset($body[$key])
            ? filter_var($body[$key], FILTER_VALIDATE_BOOL)
            : $default;

        $initialAfterDays = $intSetting('dunning_initial_after_days', $defaults->initialAfterDays, 0, 3650);
        $reminderAfterDays = $intSetting('dunning_reminder_after_days', $defaults->reminderAfterDays, 0, 3650);
        $finalAfterDays = $intSetting('dunning_final_after_days', $defaults->finalAfterDays, 0, 3650);
        $windowStartHour = $intSetting('dunning_window_start_hour', $defaults->windowStartHour, 0, 23);
        $windowEndHour = $intSetting('dunning_window_end_hour', $defaults->windowEndHour, 0, 23);
        $maxPerRun = $intSetting('dunning_max_per_run', $defaults->maxPerRun, 1, 1000);

        // Rejected rather than quietly reordered. A window whose start is not before
        // its end never opens (DunningSchedulePolicy treats it as closed), so an
        // operator who saved one would see dunning silently stop with no error to
        // explain it — the failure mode this whole feature is most likely to hit.
        if ($windowStartHour >= $windowEndHour) {
            $errors[] = new ValidationError('dunning_window_start_hour', 'dunning_window_start_hour must be before dunning_window_end_hour.', 'invalid');
        }

        // Likewise: non-ascending thresholds would let `stageFor()` pick a harsher
        // stage than the invoice's age has earned.
        if (!($initialAfterDays <= $reminderAfterDays && $reminderAfterDays <= $finalAfterDays)) {
            $errors[] = new ValidationError('dunning_reminder_after_days', 'Dunning stage thresholds must ascend: initial <= reminder <= final.', 'invalid');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $dunningSchedule = new DunningSchedule(
            isEnabled: $boolSetting('is_dunning_schedule_enabled', $defaults->isEnabled),
            initialAfterDays: $initialAfterDays,
            reminderAfterDays: $reminderAfterDays,
            finalAfterDays: $finalAfterDays,
            windowStartHour: $windowStartHour,
            windowEndHour: $windowEndHour,
            isWeekdaysOnly: $boolSetting('is_dunning_weekdays_only', $defaults->isWeekdaysOnly),
            maxPerRun: $maxPerRun,
        );

        $rawAccounts = is_array($body['bank_accounts'] ?? null) ? $body['bank_accounts'] : [];
        $newAccounts = [];
        foreach ($rawAccounts as $i => $raw) {
            if (!is_array($raw)) {
                throw new ValidationException([new ValidationError("bank_accounts.$i", 'Each bank account must be an object.', 'invalid')]);
            }
            $accountType = AccountType::tryFrom(is_string($raw['account_type'] ?? null) ? $raw['account_type'] : '');
            if ($accountType === null) {
                throw new ValidationException([new ValidationError("bank_accounts.$i.account_type", 'account_type must be "ordinary" or "current".', 'invalid')]);
            }
            $newAccounts[] = new BankAccount(
                organizationId: $organizationId,
                bankName: is_string($raw['bank_name'] ?? null) ? $raw['bank_name'] : '',
                bankBranch: is_string($raw['bank_branch'] ?? null) ? $raw['bank_branch'] : '',
                accountType: $accountType,
                accountNumber: is_string($raw['account_number'] ?? null) ? $raw['account_number'] : '',
                csvEncoding: is_string($raw['csv_encoding'] ?? null) ? $raw['csv_encoding'] : 'utf8',
                csvDateFormat: is_string($raw['csv_date_format'] ?? null) ? $raw['csv_date_format'] : 'Y/m/d',
                csvDateColumn: is_numeric($raw['csv_date_column'] ?? null) ? (int) $raw['csv_date_column'] : 0,
                csvAmountColumn: is_numeric($raw['csv_amount_column'] ?? null) ? (int) $raw['csv_amount_column'] : 1,
                csvCounterpartyColumn: is_numeric($raw['csv_counterparty_column'] ?? null) ? (int) $raw['csv_counterparty_column'] : 2,
                csvHeaderRows: is_numeric($raw['csv_header_rows'] ?? null) ? (int) $raw['csv_header_rows'] : 1,
            );
        }

        $updated = $this->useCase->execute(new UpdateClearSettingsInput(
            organizationId: $organizationId,
            upstreamBaseUrl: $upstreamBaseUrl,
            upstreamTokenRef: $upstreamTokenRef,
            dunningMinIntervalDays: $dunningMinIntervalDays,
            fiscalYearEndMonth: $fiscalYearEndMonth,
            bankAccounts: $newAccounts,
            actorUserId: AuthContext::userId($request),
            dunningSchedule: $dunningSchedule,
        ));

        return $this->response->create(ClearSettingsResponse::toArray($updated));
    }
}
