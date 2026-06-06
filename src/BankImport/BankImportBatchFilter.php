<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

/**
 * Server-side filter + sort for the bank-import-batch list (Issue #130). Every
 * field is optional; null/empty means "no constraint". `sortColumn` is
 * validated against a whitelist in the repository.
 *
 * `importedFrom` / `importedTo` are inclusive `YYYY-MM-DD` bounds on
 * `DATE(imported_at)`.
 */
final readonly class BankImportBatchFilter
{
    public function __construct(
        public ?string $sourceFilename = null,
        public ?BankImportBatchStatus $status = null,
        public ?int $rowCountMin = null,
        public ?int $rowCountMax = null,
        public ?string $importedFrom = null,
        public ?string $importedTo = null,
        public string $sortColumn = 'id',
        public string $sortDirection = 'desc',
    ) {
    }
}
