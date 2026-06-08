<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

/**
 * Summary of a receivables CSV import. Partial success is expected: valid new
 * rows are created, duplicate reference numbers are skipped, and invalid rows
 * are reported (with their CSV line number) without failing the whole import.
 */
final readonly class ImportManualReceivablesOutput
{
    /**
     * @param list<array{row: int, errors: list<array{field: string, message: string}>}> $errors
     */
    public function __construct(
        public int $created,
        public int $skipped,
        public array $errors,
    ) {
    }
}
