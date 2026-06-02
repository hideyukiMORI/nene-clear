<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

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
    public function __construct(
        private ClearSettingsRepositoryInterface $settings,
        private BankAccountRepositoryInterface $bankAccounts,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(UpdateClearSettingsInput $input): ClearSettings
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $before = $this->settings->findByOrganization($input->organizationId);

        // Replace the org's bank accounts: soft-delete the existing set, then
        // insert the new profiles. Old rows stay for import-history references.
        $this->bankAccounts->deleteByOrganization($input->organizationId, $now);
        foreach ($input->bankAccounts as $account) {
            $this->bankAccounts->save($account);
        }

        $this->settings->save(new ClearSettings(
            organizationId: $input->organizationId,
            upstreamBaseUrl: $input->upstreamBaseUrl,
            upstreamTokenRef: $input->upstreamTokenRef,
            dunningMinIntervalDays: $input->dunningMinIntervalDays,
        ));

        $updated = $this->settings->findByOrganization($input->organizationId)
            ?? new ClearSettings(
                $input->organizationId,
                $input->upstreamBaseUrl,
                $input->upstreamTokenRef,
                $input->dunningMinIntervalDays,
            );

        $this->auditEvents->record(new AuditEvent(
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
