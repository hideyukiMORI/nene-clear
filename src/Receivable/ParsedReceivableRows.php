<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

/**
 * Result of parsing a receivables CSV: the recognized header names present and
 * the data rows. Each row keeps its 1-based CSV line number so import errors can
 * point the operator at the offending line.
 */
final readonly class ParsedReceivableRows
{
    /**
     * @param list<string>                                   $headers recognized field headers present
     * @param list<array{row: int, data: array<string, string>}> $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
    ) {
    }
}
