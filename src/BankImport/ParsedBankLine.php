<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

/**
 * One parsed deposit line from a bank CSV, before persistence.
 */
final readonly class ParsedBankLine
{
    public function __construct(
        public string $valueDate,
        public int $amountCents,
        public string $counterpartyText,
        public string $lineKey,
    ) {
    }
}
