<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use NeneClear\BankImport\BankAccount;

/**
 * Validated, typed settings-update request handed from the handler to the
 * use case. `$bankAccounts` are already parsed into domain objects.
 */
final readonly class UpdateClearSettingsInput
{
    /**
     * @param list<BankAccount> $bankAccounts
     */
    public function __construct(
        public int $organizationId,
        public string $upstreamBaseUrl,
        public string $upstreamTokenRef,
        public int $dunningMinIntervalDays,
        public ?int $fiscalYearEndMonth,
        public array $bankAccounts,
        public int $actorUserId,
        /**
         * Scheduled-dunning settings (#400 §6). Defaulted so the field can be
         * added without touching every construction site — but note the endpoint
         * itself is full-replace (#284): a PUT that omits these keys resets them
         * to the defaults below, it does not leave the stored values alone.
         */
        public DunningSchedule $dunningSchedule = new DunningSchedule(),
    ) {
    }
}
