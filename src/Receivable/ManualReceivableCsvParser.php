<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

/**
 * Parses a receivables CSV by header name (not fixed column positions — unlike
 * the bank CSV, which is per-account). Recognized columns map to the same field
 * names the API uses; unknown columns are ignored. Amounts are integers in the
 * smallest currency unit (¥1 = 1), like the bank importer. Validation of each
 * row's values is the import use case's job (it reuses ManualReceivableValidator).
 */
final readonly class ManualReceivableCsvParser
{
    private const array RECOGNIZED = [
        'reference_number', 'client_name', 'total_cents',
        'recipient_email', 'due_at', 'issued_at', 'currency',
    ];

    public function parse(string $contents): ParsedReceivableRows
    {
        // Strip a UTF-8 BOM if present, then split on any newline style.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        $headerMap = null; // column index => recognized field name
        $headers = [];
        $rows = [];
        $lineNumber = 0;

        foreach ($lines as $raw) {
            ++$lineNumber;
            if (trim($raw) === '') {
                continue;
            }
            $cells = str_getcsv($raw, ',', '"', '\\');

            if ($headerMap === null) {
                $headerMap = [];
                foreach ($cells as $index => $name) {
                    $key = strtolower(trim((string) $name));
                    if (in_array($key, self::RECOGNIZED, true)) {
                        $headerMap[$index] = $key;
                        $headers[] = $key;
                    }
                }
                continue;
            }

            $data = [];
            foreach ($headerMap as $index => $field) {
                $data[$field] = trim((string) ($cells[$index] ?? ''));
            }
            $rows[] = ['row' => $lineNumber, 'data' => $data];
        }

        return new ParsedReceivableRows($headers, $rows);
    }
}
