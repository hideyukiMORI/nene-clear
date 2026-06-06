<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;
use NeneClear\BankImport\BankAccountRepositoryInterface;

/**
 * Applies an organization's Clear settings: replaces its registered bank
 * accounts (soft-deleting the previous set) and upserts the upstream/dunning
 * configuration. Records a `clear_settings_updated` audit event carrying the
 * before/after configuration so every change is reconstructable.
 */
final readonly class UpdateClearSettingsUseCase implements UpdateClearSettingsUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ClearSettingsRepositoryInterface $settings
     * @param Closure(DatabaseQueryExecutorInterface): BankAccountRepositoryInterface $bankAccounts
     * @param Closure(DatabaseQueryExecutorInterface): AuditEventRepositoryInterface $auditEvents
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $settings,
        private Closure $bankAccounts,
        private Closure $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(UpdateClearSettingsInput $input): ClearSettings
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // The bank-account replacement, the settings upsert, and the audit record
        // commit (or roll back) as one unit, so a half-applied settings change or a
        // change without its audit event can never be persisted (Issue #122).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $ex) use ($input, $now): ClearSettings {
                $settingsRepo = ($this->settings)($ex);
                $bankAccounts = ($this->bankAccounts)($ex);
                $auditEvents = ($this->auditEvents)($ex);

                $before = $settingsRepo->findByOrganization($input->organizationId);

                // Replace the org's bank accounts: soft-delete the existing set, then
                // insert the new profiles. Old rows stay for import-history references.
                $bankAccounts->deleteByOrganization($input->organizationId, $now);
                foreach ($input->bankAccounts as $account) {
                    $bankAccounts->save($account);
                }

                $settingsRepo->save(new ClearSettings(
                    organizationId: $input->organizationId,
                    upstreamBaseUrl: $input->upstreamBaseUrl,
                    upstreamTokenRef: $input->upstreamTokenRef,
                    dunningMinIntervalDays: $input->dunningMinIntervalDays,
                ));

                $updated = $settingsRepo->findByOrganization($input->organizationId)
                    ?? new ClearSettings(
                        $input->organizationId,
                        $input->upstreamBaseUrl,
                        $input->upstreamTokenRef,
                        $input->dunningMinIntervalDays,
                    );

                $auditEvents->record(new AuditEvent(
                    organizationId: $input->organizationId,
                    eventType: 'clear_settings_updated',
                    actorUserId: $input->actorUserId,
                    occurredAt: $now,
                    payload: [
                        'before' => $before !== null ? self::snapshot($before) : null,
                        'after' => self::snapshot($updated),
                    ],
                ));

                return $updated;
            },
        );
    }

    /**
     * Audit snapshot. `upstream_token_ref` is the env-var *name* (not the
     * secret), so it is safe to record (compliance §15).
     *
     * @return array<string, mixed>
     */
    private static function snapshot(ClearSettings $settings): array
    {
        return [
            'upstream_base_url' => $settings->upstreamBaseUrl,
            'upstream_token_ref' => $settings->upstreamTokenRef,
            'dunning_min_interval_days' => $settings->dunningMinIntervalDays,
            'bank_account_numbers' => array_map(
                static fn ($account): string => $account->accountNumber,
                $settings->bankAccounts,
            ),
        ];
    }
}
