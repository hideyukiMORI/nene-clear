<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\BankCsvParser;
use PHPUnit\Framework\TestCase;

/**
 * Boundary cases for {@see BankCsvParser}. Deposit detection, encoding,
 * line endings, header rows, and lineKey uniqueness.
 */
final class BankCsvParserBoundaryTest extends TestCase
{
    private function account(int $headerRows = 1): BankAccount
    {
        // Columns: 0=取引日, 1=入金額, 2=出金額, 3=摘要
        return new BankAccount(
            organizationId: 7,
            bankName: 'Test Bank',
            bankBranch: 'Main',
            accountType: AccountType::Ordinary,
            accountNumber: '1234567',
            csvEncoding: 'utf8',
            csvDateFormat: 'Y/m/d',
            csvDateColumn: 0,
            csvAmountColumn: 1,
            csvCounterpartyColumn: 3,
            csvHeaderRows: $headerRows,
        );
    }

    /**
     * @return list<\NeneClear\BankImport\ParsedBankLine>
     */
    private function parse(string $csv, int $headerRows = 1): array
    {
        return (new BankCsvParser())->parse($csv, $this->account($headerRows));
    }

    public function test_empty_file_yields_no_lines(): void
    {
        self::assertSame([], $this->parse(''));
    }

    public function test_header_only_file_yields_no_lines(): void
    {
        self::assertSame([], $this->parse("取引日,入金額,出金額,摘要\n"));
    }

    public function test_zero_amount_is_skipped(): void
    {
        $csv = "取引日,入金額,出金額,摘要\n2026/04/20,0,,Zero\n";
        self::assertSame([], $this->parse($csv));
    }

    public function test_negative_amount_is_skipped(): void
    {
        // A negative deposit column is not a credit line.
        $csv = "取引日,入金額,出金額,摘要\n2026/04/20,-1000,,Neg\n";
        self::assertSame([], $this->parse($csv));
    }

    public function test_blank_and_whitespace_lines_are_skipped(): void
    {
        $csv = "取引日,入金額,出金額,摘要\n"
            . "2026/04/20,1000,,A\n"
            . "\n"
            . "   \n"
            . "2026/04/21,2000,,B\n";

        $lines = $this->parse($csv);

        self::assertCount(2, $lines);
        self::assertSame(1000, $lines[0]->amountCents);
        self::assertSame(2000, $lines[1]->amountCents);
    }

    public function test_multiple_header_rows_are_skipped(): void
    {
        $csv = "BANK STATEMENT EXPORT\n"
            . "取引日,入金額,出金額,摘要\n"
            . "2026/04/20,1000,,A\n";

        $lines = $this->parse($csv, headerRows: 2);

        self::assertCount(1, $lines);
        self::assertSame('2026-04-20', $lines[0]->valueDate);
    }

    public function test_crlf_and_cr_line_endings(): void
    {
        $crlf = "取引日,入金額,出金額,摘要\r\n2026/04/20,1000,,A\r\n2026/04/21,2000,,B\r\n";
        self::assertCount(2, $this->parse($crlf));

        $cr = "取引日,入金額,出金額,摘要\r2026/04/20,1000,,A\r";
        self::assertCount(1, $this->parse($cr));
    }

    public function test_amount_with_currency_symbol_and_commas(): void
    {
        $csv = "取引日,入金額,出金額,摘要\n2026/04/20,\"¥1,234,567\",,Big\n";

        $lines = $this->parse($csv);

        self::assertCount(1, $lines);
        self::assertSame(1234567, $lines[0]->amountCents);
    }

    public function test_missing_amount_cell_is_skipped(): void
    {
        // Row shorter than the amount column index → treated as non-credit.
        $csv = "取引日,入金額,出金額,摘要\n2026/04/20\n";
        self::assertSame([], $this->parse($csv));
    }

    public function test_missing_counterparty_cell_yields_empty_string(): void
    {
        $csv = "取引日,入金額,出金額,摘要\n2026/04/20,1000\n";

        $lines = $this->parse($csv);

        self::assertCount(1, $lines);
        self::assertSame('', $lines[0]->counterpartyText);
    }

    public function test_identical_rows_at_different_positions_get_distinct_line_keys(): void
    {
        // lineKey incorporates row index, so duplicate deposits are distinguishable.
        $csv = "取引日,入金額,出金額,摘要\n"
            . "2026/04/20,1000,,SAME\n"
            . "2026/04/20,1000,,SAME\n";

        $lines = $this->parse($csv);

        self::assertCount(2, $lines);
        self::assertNotSame($lines[0]->lineKey, $lines[1]->lineKey);
    }

    public function test_file_without_trailing_newline(): void
    {
        $csv = "取引日,入金額,出金額,摘要\n2026/04/20,1000,,A";

        $lines = $this->parse($csv);

        self::assertCount(1, $lines);
        self::assertSame(1000, $lines[0]->amountCents);
    }
}
