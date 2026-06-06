<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

final readonly class BankTransactionFilter
{
    public function __construct(
        public ?BankTransactionStatus $status = null,
        public ?string $valueDateFrom = null,
        public ?string $valueDateTo = null,
        public ?int $amountMinCents = null,
        public ?int $amountMaxCents = null,
        public ?string $counterparty = null,
        public string $sortColumn = 'value_date',
        public string $sortDirection = 'desc',
    ) {
    }
}
