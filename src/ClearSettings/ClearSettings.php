<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use NeneClear\BankImport\BankAccount;

final readonly class ClearSettings
{
    /**
     * @param list<BankAccount> $bankAccounts
     */
    public function __construct(
        public int $organizationId,
        public string $upstreamBaseUrl,
        public string $upstreamTokenRef,
        public int $dunningMinIntervalDays,
        public ?int $fiscalYearEndMonth = null,
        public array $bankAccounts = [],
        /**
         * Scheduled dunning (#400). Read-only for now: the repository loads these
         * from `clear_settings` but does NOT write them, because
         * `PUT /admin/clear-settings` is a full replace (#284) and its request
         * cannot carry them yet. Including them in the UPDATE would let an
         * unrelated settings save silently switch the schedule back off. The
         * write path lands with the settings API/UI (design §11 step 4).
         */
        public DunningSchedule $dunningSchedule = new DunningSchedule(),
    ) {
    }
}
