<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\BankImport\AccountType;
use NeneClear\BankImport\BankAccount;
use NeneClear\BankImport\BankAccountRepositoryInterface;
use NeneClear\BankImport\BankCsvParser;
use NeneClear\BankImport\BankImportBatchRepositoryInterface;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\BankImport\ImportBankCsvInput;
use NeneClear\BankImport\ImportBankCsvUseCase;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\BankImport\PdoBankImportBatchRepository;
use NeneClear\BankImport\PdoBankTransactionRepository;
use NeneClear\Tests\Audit\ThrowingAuditEventRepository;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Proves the audit atomicity guarantee (Issue #122): when the audit record fails
 * mid-use-case, the framework transaction rolls back the business writes too, so
 * a state change can never be persisted without its audit event.
 *
 * Uses the real PdoDatabaseTransactionManager (via DatabaseTestKit) over a file
 * SQLite database and repositories built from the transaction's executor.
 */
final class AuditAtomicityTest extends TestCase
{
    private const string CSV = "date,deposit,withdrawal,memo\n2026/04/20,110000,,ACME\n2026/04/22,50000,,TARO\n";

    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-atomicity-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $this->query = $kit->queryExecutor;

        SchemaFixture::createBankAccounts($this->query);
        SchemaFixture::createBankImportBatches($this->query);
        SchemaFixture::createBankTransactions($this->query);
        SchemaFixture::createAuditEvents($this->query);

        (new PdoBankAccountRepository($this->query))->save(new BankAccount(
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

        // Build every repository from the transaction's executor so the rollback
        // actually undoes the writes; the audit repository always throws.
        $this->useCase = new ImportBankCsvUseCase(
            $kit->transactionManager,
            static fn (DatabaseQueryExecutorInterface $tx): BankAccountRepositoryInterface => new PdoBankAccountRepository($tx),
            static fn (DatabaseQueryExecutorInterface $tx): BankImportBatchRepositoryInterface => new PdoBankImportBatchRepository($tx),
            static fn (DatabaseQueryExecutorInterface $tx): BankTransactionRepositoryInterface => new PdoBankTransactionRepository($tx),
            static fn (DatabaseQueryExecutorInterface $tx): ThrowingAuditEventRepository => new ThrowingAuditEventRepository(),
            new BankCsvParser(),
            new FixedClock(),
        );
    }

    private ImportBankCsvUseCase $useCase;

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function test_failed_audit_rolls_back_the_business_writes(): void
    {
        try {
            $this->useCase->execute(new ImportBankCsvInput(
                organizationId: 7,
                bankAccountId: 1,
                contents: self::CSV,
                sourceFilename: 'april.csv',
                actorUserId: 42,
            ));
            self::fail('Expected the simulated audit failure to propagate.');
        } catch (RuntimeException $e) {
            self::assertSame('audit write failed (simulated)', $e->getMessage());
        }

        // The batch, its transactions, and the audit event must all be absent:
        // the transaction rolled back as one unit.
        self::assertSame(0, $this->rowCount('bank_import_batches'), 'batch must be rolled back');
        self::assertSame(0, $this->rowCount('bank_transactions'), 'transactions must be rolled back');
        self::assertSame(0, $this->rowCount('audit_events'), 'no audit event persisted');
    }

    private function rowCount(string $table): int
    {
        $row = $this->query->fetchOne("SELECT COUNT(*) AS n FROM {$table}");

        return (int) ($row['n'] ?? 0);
    }
}
