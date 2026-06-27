<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

/**
 * Parses a receivables CSV by header name (not fixed column positions). Each
 * column header is matched against a dictionary of aliases — including the
 * common Japanese headers that freee / マネーフォワード / 弥生 / Misoca exports
 * use — and mapped to the canonical API field names; the first column matching a
 * field wins, and unknown columns are ignored. Shift-JIS (CP932) input is
 * detected and transcoded to UTF-8, and amount cells are normalized (¥ / commas
 * / 円 / full-width digits stripped) so real exports import without manual
 * remapping. Amounts are integers in the smallest currency unit (¥1 = 1).
 * Validation of each row's values is the import use case's job (it reuses
 * {@see ManualReceivableValidator}).
 */
final readonly class ManualReceivableCsvParser
{
    /**
     * Canonical field => recognized header aliases (matched case-insensitively).
     * Keep the canonical English name in each list so existing/templated CSVs
     * keep working.
     *
     * @var array<string, list<string>>
     */
    private const array ALIASES = [
        'reference_number' => [
            'reference_number', 'invoice_number', 'document_number', 'reference', 'ref', 'no',
            '番号', '請求書番号', '請求番号', '請求no', '伝票番号', '文書番号', '帳票番号',
        ],
        'client_name' => [
            'client_name', 'customer_name', 'customer', 'client', 'payer',
            '取引先', '取引先名', '取引先名称', '得意先', '得意先名', '顧客', '顧客名', '宛名', '会社名', '請求先', '請求先名',
        ],
        'total_cents' => [
            'total_cents', 'total_amount', 'amount', 'total',
            '金額', '合計', '合計金額', '請求金額', '請求額', '請求合計', '税込金額', '税込合計', '総額', 'ご請求金額',
        ],
        'recipient_email' => [
            'recipient_email', 'email', 'e-mail', 'mail',
            'メール', 'メールアドレス', '宛先メール', '送信先',
        ],
        'due_at' => [
            'due_at', 'due_date', 'payment_due', 'due',
            '支払期日', '支払期限', '入金期日', '期日', 'お支払期限', '振込期限', '支払予定日',
        ],
        'issued_at' => [
            'issued_at', 'issue_date', 'invoice_date', 'issued', 'date',
            '発行日', '請求日', '請求年月日', '発行年月日', '取引日', '日付',
        ],
        'currency' => [
            'currency', '通貨',
        ],
    ];

    /** Fields whose values are numeric amounts and get separator/symbol stripping. */
    private const array AMOUNT_FIELDS = ['total_cents'];

    public function parse(string $contents): ParsedReceivableRows
    {
        // Accept Shift-JIS (CP932) exports (弥生 et al.): if the bytes are not
        // valid UTF-8, transcode from CP932. ASCII-only content is already valid
        // UTF-8, so this only kicks in for non-UTF-8 multibyte files.
        if (!mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'SJIS-win') ?: $contents;
        }

        // Strip a UTF-8 BOM if present, then split on any newline style.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        $lookup = self::aliasLookup();

        $headerMap = null; // column index => canonical field name
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
                $claimed = [];
                foreach ($cells as $index => $name) {
                    $field = $lookup[self::normalizeHeader((string) $name)] ?? null;
                    if ($field === null || isset($claimed[$field])) {
                        continue; // unknown column, or this field already taken (first wins)
                    }
                    $headerMap[$index] = $field;
                    $claimed[$field] = true;
                    $headers[] = $field;
                }
                continue;
            }

            $data = [];
            foreach ($headerMap as $index => $field) {
                $value = trim((string) ($cells[$index] ?? ''));
                if (in_array($field, self::AMOUNT_FIELDS, true)) {
                    $value = self::normalizeAmount($value);
                }
                $data[$field] = $value;
            }
            $rows[] = ['row' => $lineNumber, 'data' => $data];
        }

        return new ParsedReceivableRows($headers, $rows);
    }

    /**
     * Lower-cased alias => canonical field, for O(1) header matching.
     *
     * @return array<string, string>
     */
    private static function aliasLookup(): array
    {
        $lookup = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $lookup[mb_strtolower($alias, 'UTF-8')] = $field;
            }
        }

        return $lookup;
    }

    /** Trim ASCII + full-width whitespace and surrounding quotes, then lower-case. */
    private static function normalizeHeader(string $name): string
    {
        $name = preg_replace('/^[\s\x{3000}"\']+|[\s\x{3000}"\']+$/u', '', $name) ?? $name;

        return mb_strtolower($name, 'UTF-8');
    }

    /**
     * Normalize an amount cell to a bare integer string: full-width digits to
     * half-width, then strip currency symbols, separators, spaces, and 円.
     */
    private static function normalizeAmount(string $value): string
    {
        $value = mb_convert_kana($value, 'n', 'UTF-8');

        return preg_replace('/[¥￥,，、\s\x{3000}円]/u', '', $value) ?? $value;
    }
}
