<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Audit\AuditRecorder;
use NeneClear\Audit\PdoAuditEventRepository;
use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\BankCsvParser;
use NeneClear\BankImport\ImportBankCsvInput;
use NeneClear\BankImport\ImportBankCsvUseCase;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\BankImport\PdoBankImportBatchRepository;
use NeneClear\BankImport\PdoBankTransactionRepository;
use NeneClear\Tests\Support\FakeTransactionManager;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class ImportBankCsvPdoTest extends TestCase
{
    private const string CSV = "date,deposit,withdrawal,memo\n2026/04/20,110000,,ACME\n2026/04/21,,5000,ATM\n2026/04/22,50000,,TARO\n";

    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private ImportBankCsvUseCase $useCase;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-import-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createBankAccounts($this->query);
        SchemaFixture::createBankImportBatches($this->query);
        SchemaFixture::createBankTransactions($this->query);
        SchemaFixture::createAuditEvents($this->query);

        $accounts = new PdoBankAccountRepository($this->query);
        $accounts->save(new BankAccount(
            organizationId: 7,
            bankName: 'Test Bank',
            bankBranch: 'Main',
            accountType: AccountType::Ordinary,
            accountNumber: '123',
            csvEncoding: 'utf8',
            csvDateFormat: 'Y/m/d',
            csvDateColumn: 0,
            csvAmountColumn: 1,
            csvCounterpartyColumn: 3,
            csvHeaderRows: 1,
        ));

        $batches = new PdoBankImportBatchRepository($this->query);
        $transactions = new PdoBankTransactionRepository($this->query);

        $this->useCase = new ImportBankCsvUseCase(
            new FakeTransactionManager(),
            fn () => $accounts,
            fn () => $batches,
            fn () => $transactions,
            fn () => new AuditRecorder(new PdoAuditEventRepository($this->query)),
            new BankCsvParser(),
            new FixedClock(),
        );
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function input(string $contents): ImportBankCsvInput
    {
        return new ImportBankCsvInput(
            organizationId: 7,
            bankAccountId: 1,
            sourceFilename: 'april.csv',
            contents: $contents,
            actorUserId: 42,
        );
    }

    public function test_import_persists_immutable_deposit_lines_and_audit(): void
    {
        $output = $this->useCase->execute($this->input(self::CSV));

        self::assertSame(2, $output->rowCount); // withdrawal skipped

        $rows = (new PdoBankTransactionRepository($this->query))->findByBatch($output->bankImportBatchId);
        self::assertCount(2, $rows);
        self::assertSame('2026-04-20', $rows[0]->valueDate);
        self::assertSame(110000, $rows[0]->amountCents);
        self::assertSame('unmatched', $rows[0]->status->value);

        $audit = $this->query->fetchOne('SELECT event_type, actor_user_id FROM audit_events WHERE event_type = ?', ['bank_import']);
        self::assertNotNull($audit);
        self::assertSame(42, (int) $audit['actor_user_id']);
    }

    public function test_reimporting_same_file_is_blocked(): void
    {
        $this->useCase->execute($this->input(self::CSV));

        $this->expectException(\NeneClear\BankImport\DuplicateBankImportException::class);
        $this->useCase->execute($this->input(self::CSV));
    }
}
