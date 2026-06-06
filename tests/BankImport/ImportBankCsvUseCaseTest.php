<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\BankAccountNotFoundException;
use NeneClear\BankImport\BankCsvParser;
use NeneClear\BankImport\DuplicateBankImportException;
use NeneClear\BankImport\ImportBankCsvInput;
use NeneClear\BankImport\ImportBankCsvUseCase;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Support\FakeTransactionManager;
use NeneClear\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class ImportBankCsvUseCaseTest extends TestCase
{
    private const string CSV = "date,deposit,withdrawal,memo\n2026/04/20,110000,,ACME\n2026/04/22,50000,,TARO\n";

    private InMemoryBankAccountRepository $accounts;
    private InMemoryBankImportBatchRepository $batches;
    private InMemoryBankTransactionRepository $transactions;
    private InMemoryAuditEventRepository $audit;
    private ImportBankCsvUseCase $useCase;

    protected function setUp(): void
    {
        $this->accounts = new InMemoryBankAccountRepository();
        $this->accounts->save(new BankAccount(
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

        $this->batches = new InMemoryBankImportBatchRepository();
        $this->transactions = new InMemoryBankTransactionRepository();
        $this->audit = new InMemoryAuditEventRepository();

        $this->useCase = new ImportBankCsvUseCase(
            new FakeTransactionManager(),
            fn () => $this->accounts,
            fn () => $this->batches,
            fn () => $this->transactions,
            fn () => $this->audit,
            new BankCsvParser(),
            new FixedClock(),
        );
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

    public function test_import_creates_batch_transactions_and_audit_event(): void
    {
        $output = $this->useCase->execute($this->input(self::CSV));

        self::assertSame(2, $output->rowCount);
        self::assertSame(64, strlen($output->fileHash)); // sha-256 hex
        self::assertSame(2, $this->transactions->countByBatch($output->bankImportBatchId));

        self::assertCount(1, $this->audit->events);
        self::assertSame('bank_import', $this->audit->events[0]->eventType);
        self::assertSame(42, $this->audit->events[0]->actorUserId);
    }

    public function test_duplicate_file_hash_is_rejected(): void
    {
        $this->useCase->execute($this->input(self::CSV));

        $this->expectException(DuplicateBankImportException::class);
        $this->useCase->execute($this->input(self::CSV));
    }

    public function test_unknown_or_cross_tenant_account_is_rejected(): void
    {
        $this->expectException(BankAccountNotFoundException::class);
        $this->useCase->execute(new ImportBankCsvInput(
            organizationId: 999,
            bankAccountId: 1,
            sourceFilename: 'x.csv',
            contents: self::CSV,
            actorUserId: 42,
        ));
    }
}
