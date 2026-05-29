<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

/**
 * One imported bank deposit line. Immutable evidence: amount, value date, and
 * counterparty text are never edited after import (compliance §3.1 / ADR 0012);
 * only `status` changes through reconciliation, and corrections are reversal
 * import batches.
 */
final readonly class BankTransaction
{
    public function __construct(
        public int $organizationId,
        public int $bankImportBatchId,
        public int $bankAccountId,
        public string $valueDate,
        public int $amountCents,
        public string $counterpartyText,
        public string $lineKey,
        public BankTransactionStatus $status,
        public ?int $id = null,
    ) {
    }
}
