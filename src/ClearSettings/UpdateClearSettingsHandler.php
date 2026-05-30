<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\BankAccountRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UpdateClearSettingsHandler
{
    public function __construct(
        private ClearSettingsRepositoryInterface $settings,
        private BankAccountRepositoryInterface $bankAccounts,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $body = (array) $request->getParsedBody();
        $errors = [];

        $upstreamBaseUrl = is_string($body['upstream_base_url'] ?? null) ? trim($body['upstream_base_url']) : '';
        $upstreamTokenRef = is_string($body['upstream_token_ref'] ?? null) ? trim($body['upstream_token_ref']) : '';

        $dunningMinIntervalDaysRaw = $body['dunning_min_interval_days'] ?? 7;
        $dunningMinIntervalDays = is_numeric($dunningMinIntervalDaysRaw) ? (int) $dunningMinIntervalDaysRaw : 0;
        if ($dunningMinIntervalDays < 1) {
            $errors[] = new ValidationError('dunning_min_interval_days', 'dunning_min_interval_days must be at least 1.', 'invalid');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

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

        $this->bankAccounts->deleteByOrganization($organizationId);
        foreach ($newAccounts as $account) {
            $this->bankAccounts->save($account);
        }

        $this->settings->save(new ClearSettings(
            organizationId: $organizationId,
            upstreamBaseUrl: $upstreamBaseUrl,
            upstreamTokenRef: $upstreamTokenRef,
            dunningMinIntervalDays: $dunningMinIntervalDays,
        ));

        $updated = $this->settings->findByOrganization($organizationId)
            ?? new ClearSettings($organizationId, $upstreamBaseUrl, $upstreamTokenRef, $dunningMinIntervalDays);

        return $this->response->create(ClearSettingsResponse::toArray($updated));
    }
}
