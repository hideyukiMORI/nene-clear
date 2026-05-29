<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

/**
 * A registered company bank account plus its CSV import profile. The profile
 * is what lets one bank's CSV format be parsed; `value_date` sourcing per
 * account is documentable (compliance §2.2).
 */
final readonly class BankAccount
{
    public function __construct(
        public int $organizationId,
        public string $bankName,
        public string $bankBranch,
        public AccountType $accountType,
        public string $accountNumber,
        public string $csvEncoding,
        public string $csvDateFormat,
        public int $csvDateColumn,
        public int $csvAmountColumn,
        public int $csvCounterpartyColumn,
        public int $csvHeaderRows,
        public ?int $id = null,
    ) {
    }
}
