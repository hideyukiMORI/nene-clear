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
    ) {
    }
}
