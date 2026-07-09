<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use DateTimeImmutable;

/**
 * Parses a bank CSV into deposit lines using a {@see BankAccount}'s profile.
 *
 * Only credits (deposits) are kept: rows whose amount column is empty or not a
 * positive value are skipped (they are withdrawals). Bank CSV cells carry
 * integer yen; the parsed amount is stored in the app-wide `_cents` unit
 * (1 yen = 100 cents, terminology registry §3), so imported deposits compare
 * directly against receivable `outstanding_cents` (#261). Deposit rows with an
 * unparseable value date fail the whole import rather than being dropped
 * silently (compliance §3.1 — no silent loss of evidence).
 */
final readonly class BankCsvParser
{
    /**
     * @return list<ParsedBankLine>
     *
     * @throws InvalidBankCsvException
     */
    public function parse(string $contents, BankAccount $account): array
    {
        if (strtolower($account->csvEncoding) !== 'utf8' && strtolower($account->csvEncoding) !== 'utf-8') {
            $converted = @mb_convert_encoding($contents, 'UTF-8', $this->mbEncoding($account->csvEncoding));
            $contents = is_string($converted) ? $converted : $contents;
        }

        $rows = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        $lines = [];
        $index = -1;

        foreach ($rows as $raw) {
            ++$index;

            if ($index < $account->csvHeaderRows || trim($raw) === '') {
                continue;
            }

            $cells = str_getcsv($raw, ',', '"', '\\');

            $amountCents = $this->parseAmount($cells[$account->csvAmountColumn] ?? null);
            if ($amountCents <= 0) {
                // Empty/zero/negative deposit column → not a credit line; skip.
                continue;
            }

            $valueDate = $this->parseDate((string) ($cells[$account->csvDateColumn] ?? ''), $account->csvDateFormat);
            $counterparty = trim((string) ($cells[$account->csvCounterpartyColumn] ?? ''));
            $lineKey = md5($index . '|' . $valueDate . '|' . $amountCents . '|' . $counterparty);

            $lines[] = new ParsedBankLine(
                valueDate: $valueDate,
                amountCents: $amountCents,
                counterpartyText: $counterparty,
                lineKey: $lineKey,
            );
        }

        return $lines;
    }

    private function parseAmount(?string $raw): int
    {
        if ($raw === null) {
            return 0;
        }

        $digits = preg_replace('/[^\d-]/', '', $raw) ?? '';

        return $digits === '' || $digits === '-' ? 0 : (int) $digits * 100;
    }

    /**
     * @throws InvalidBankCsvException
     */
    private function parseDate(string $raw, string $format): string
    {
        $date = DateTimeImmutable::createFromFormat($format, trim($raw));

        if ($date === false) {
            throw new InvalidBankCsvException(sprintf('Could not parse date "%s" with format "%s".', $raw, $format));
        }

        return $date->format('Y-m-d');
    }

    private function mbEncoding(string $encoding): string
    {
        return match (strtolower($encoding)) {
            'shift_jis', 'shift-jis', 'sjis', 'cp932', 'ms932' => 'SJIS-win',
            default => $encoding,
        };
    }
}
