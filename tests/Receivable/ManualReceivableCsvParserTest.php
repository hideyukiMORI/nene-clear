<?php

declare(strict_types=1);

namespace NeneClear\Tests\Receivable;

use NeneClear\Receivable\ManualReceivableCsvParser;
use PHPUnit\Framework\TestCase;

final class ManualReceivableCsvParserTest extends TestCase
{
    public function testRecognizesCanonicalHeaders(): void
    {
        $csv = "reference_number,client_name,total_cents\nINV-1,Acme,110000\n";

        $parsed = (new ManualReceivableCsvParser())->parse($csv);

        self::assertSame(['reference_number', 'client_name', 'total_cents'], $parsed->headers);
        self::assertSame('110000', $parsed->rows[0]['data']['total_cents']);
        self::assertSame('Acme', $parsed->rows[0]['data']['client_name']);
    }

    public function testRecognizesJapaneseAliasesFromTypicalExport(): void
    {
        $csv = "請求書番号,取引先名,請求金額,支払期日\nINV-9,株式会社アクメ,\"￥110,000\",2026/04/30\n";

        $parsed = (new ManualReceivableCsvParser())->parse($csv);

        self::assertContains('reference_number', $parsed->headers);
        self::assertContains('client_name', $parsed->headers);
        self::assertContains('total_cents', $parsed->headers);
        self::assertContains('due_at', $parsed->headers);

        $data = $parsed->rows[0]['data'];
        self::assertSame('INV-9', $data['reference_number']);
        self::assertSame('株式会社アクメ', $data['client_name']);
        self::assertSame('110000', $data['total_cents']); // ¥ and comma stripped
        self::assertSame('2026/04/30', $data['due_at']);
    }

    public function testNormalizesFullWidthAmountAndYenSuffix(): void
    {
        $csv = "請求書番号,取引先,金額\nA,B,１１０，０００円\n";

        $data = (new ManualReceivableCsvParser())->parse($csv)->rows[0]['data'];

        self::assertSame('110000', $data['total_cents']);
    }

    public function testReadsShiftJisInput(): void
    {
        $utf8 = "請求書番号,取引先名,合計金額\nINV-3,みどり商事,50000\n";
        $sjis = mb_convert_encoding($utf8, 'SJIS-win', 'UTF-8');

        $parsed = (new ManualReceivableCsvParser())->parse($sjis);

        self::assertContains('client_name', $parsed->headers);
        self::assertSame('みどり商事', $parsed->rows[0]['data']['client_name']);
        self::assertSame('50000', $parsed->rows[0]['data']['total_cents']);
    }

    public function testFirstColumnWinsWhenTwoMapToTheSameField(): void
    {
        // Both 金額 and 合計金額 map to total_cents; the first column wins.
        $csv = "請求書番号,取引先,金額,合計金額\nA,B,100,999\n";

        $parsed = (new ManualReceivableCsvParser())->parse($csv);

        self::assertSame(1, array_count_values($parsed->headers)['total_cents']);
        self::assertSame('100', $parsed->rows[0]['data']['total_cents']);
    }

    public function testIgnoresUnknownColumns(): void
    {
        $csv = "請求書番号,メモ,取引先,金額\nINV-7,社内用,Acme,1000\n";

        $parsed = (new ManualReceivableCsvParser())->parse($csv);

        self::assertSame(['reference_number', 'client_name', 'total_cents'], $parsed->headers);
        self::assertArrayNotHasKey('メモ', $parsed->rows[0]['data']);
    }
}
