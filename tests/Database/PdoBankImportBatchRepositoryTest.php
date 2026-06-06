<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\BankImport\BankImportBatch;
use NeneClear\BankImport\BankImportBatchFilter;
use NeneClear\BankImport\BankImportBatchStatus;
use NeneClear\BankImport\PdoBankImportBatchRepository;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoBankImportBatchRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private PdoBankImportBatchRepository $repo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-batch-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createBankImportBatches($this->query);
        $this->repo = new PdoBankImportBatchRepository($this->query);

        $this->seed('march.csv', 10, BankImportBatchStatus::Imported, '2026-03-10 09:00:00');
        $this->seed('april.csv', 30, BankImportBatchStatus::Reversed, '2026-04-15 09:00:00');
        $this->seed('april-2.csv', 20, BankImportBatchStatus::Imported, '2026-04-20 09:00:00');
        $this->seed('other.csv', 5, BankImportBatchStatus::Imported, '2026-05-01 09:00:00', orgId: 99);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function seed(string $file, int $rows, BankImportBatchStatus $status, string $importedAt, int $orgId = 7): void
    {
        $this->repo->save(new BankImportBatch(
            organizationId: $orgId,
            bankAccountId: 1,
            fileHash: bin2hex(random_bytes(6)),
            sourceFilename: $file,
            rowCount: $rows,
            status: $status,
            importedBy: 1,
            importedAt: $importedAt,
        ));
    }

    public function test_filename_status_and_rowcount_filters(): void
    {
        self::assertCount(2, $this->repo->findByOrganization(7, new BankImportBatchFilter(sourceFilename: 'april'), 50, 0));
        self::assertCount(2, $this->repo->findByOrganization(7, new BankImportBatchFilter(status: BankImportBatchStatus::Imported), 50, 0));
        self::assertCount(2, $this->repo->findByOrganization(7, new BankImportBatchFilter(rowCountMin: 20), 50, 0));
        self::assertSame(2, $this->repo->countByOrganization(7, new BankImportBatchFilter(status: BankImportBatchStatus::Imported)));
    }

    public function test_imported_date_range(): void
    {
        $f = new BankImportBatchFilter(importedFrom: '2026-04-01', importedTo: '2026-04-30');
        self::assertCount(2, $this->repo->findByOrganization(7, $f, 50, 0));
    }

    public function test_sort_by_row_count_ascending(): void
    {
        $rows = $this->repo->findByOrganization(7, new BankImportBatchFilter(sortColumn: 'row_count', sortDirection: 'asc'), 50, 0);
        $counts = array_map(static fn (BankImportBatch $b): int => $b->rowCount, $rows);
        self::assertSame([10, 20, 30], $counts);
    }
}
