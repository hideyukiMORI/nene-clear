<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\BankCsvParser;
use NeneClear\BankImport\InvalidBankCsvException;
use PHPUnit\Framework\TestCase;

final class BankCsvParserTest extends TestCase
{
    private function account(string $encoding): BankAccount
    {
        // Columns: 0=取引日, 1=入金額, 2=出金額, 3=摘要
        return new BankAccount(
            organizationId: 7,
            bankName: 'Test Bank',
            bankBranch: 'Main',
            accountType: AccountType::Ordinary,
            accountNumber: '1234567',
            csvEncoding: $encoding,
            csvDateFormat: 'Y/m/d',
            csvDateColumn: 0,
            csvAmountColumn: 1,
            csvCounterpartyColumn: 3,
            csvHeaderRows: 1,
        );
    }

    public function test_parses_deposits_only_and_normalizes_dates_from_shift_jis(): void
    {
        $utf8 = "取引日,入金額,出金額,摘要\n"
            . "2026/04/20,110000,,カ）アクメ\n"
            . "2026/04/21,,5000,ATM出金\n"
            . "2026/04/22,\"50,000\",,フリコミ タロウ\n";
        $sjis = (string) mb_convert_encoding($utf8, 'SJIS-win', 'UTF-8');

        $lines = (new BankCsvParser())->parse($sjis, $this->account('shift_jis'));

        self::assertCount(2, $lines); // the withdrawal row is skipped
        self::assertSame('2026-04-20', $lines[0]->valueDate);
        self::assertSame(110000, $lines[0]->amountCents);
        self::assertSame('カ）アクメ', $lines[0]->counterpartyText);
        self::assertSame('2026-04-22', $lines[1]->valueDate);
        self::assertSame(50000, $lines[1]->amountCents); // quoted "50,000"
        self::assertSame('フリコミ タロウ', $lines[1]->counterpartyText);
    }

    public function test_throws_on_unparseable_deposit_date(): void
    {
        $csv = "取引日,入金額,出金額,摘要\nnot-a-date,1000,,X\n";

        $this->expectException(InvalidBankCsvException::class);
        (new BankCsvParser())->parse($csv, $this->account('utf8'));
    }
}
