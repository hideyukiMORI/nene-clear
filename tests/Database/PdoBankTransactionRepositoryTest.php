<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\BankImport\BankTransaction;
use NeneClear\BankImport\BankTransactionFilter;
use NeneClear\BankImport\BankTransactionStatus;
use NeneClear\BankImport\PdoBankTransactionRepository;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoBankTransactionRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private PdoBankTransactionRepository $repo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-tx-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createBankTransactions($this->query);

        $this->repo = new PdoBankTransactionRepository($this->query);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function tx(
        int $orgId = 7,
        int $batchId = 1,
        string $valueDate = '2026-04-20',
        int $amountCents = 100000,
        string $counterpartyText = 'ACME',
        BankTransactionStatus $status = BankTransactionStatus::Unmatched,
    ): int {
        return $this->repo->save(new BankTransaction(
            organizationId: $orgId,
            bankImportBatchId: $batchId,
            bankAccountId: 1,
            valueDate: $valueDate,
            amountCents: $amountCents,
            counterpartyText: $counterpartyText,
            lineKey: bin2hex(random_bytes(8)),
            status: $status,
        ));
    }

    public function test_findById_returns_own_org_transaction(): void
    {
        $id = $this->tx(orgId: 7);
        $tx = $this->repo->findById(7, $id);

        self::assertNotNull($tx);
        self::assertSame($id, $tx->id);
        self::assertSame(7, $tx->organizationId);
    }

    public function test_findById_cross_tenant_returns_null(): void
    {
        $id = $this->tx(orgId: 7);

        self::assertNull($this->repo->findById(999, $id));
    }

    public function test_findById_missing_returns_null(): void
    {
        self::assertNull($this->repo->findById(7, 9999));
    }

    public function test_filter_by_status(): void
    {
        $this->tx(status: BankTransactionStatus::Unmatched);
        $this->tx(status: BankTransactionStatus::Matched);

        $filter = new BankTransactionFilter(status: BankTransactionStatus::Unmatched);
        self::assertCount(1, $this->repo->findByOrganization(7, $filter, 50, 0));
        self::assertSame(1, $this->repo->countByOrganization(7, $filter));
    }

    public function test_filter_by_date_range(): void
    {
        $this->tx(valueDate: '2026-04-01');
        $this->tx(valueDate: '2026-04-15');
        $this->tx(valueDate: '2026-04-30');

        $filter = new BankTransactionFilter(valueDateFrom: '2026-04-10', valueDateTo: '2026-04-20');
        $results = $this->repo->findByOrganization(7, $filter, 50, 0);

        self::assertCount(1, $results);
        self::assertSame('2026-04-15', $results[0]->valueDate);
    }

    public function test_filter_by_amount_range(): void
    {
        $this->tx(amountCents: 50000);
        $this->tx(amountCents: 100000);
        $this->tx(amountCents: 200000);

        $filter = new BankTransactionFilter(amountMinCents: 80000, amountMaxCents: 150000);
        $results = $this->repo->findByOrganization(7, $filter, 50, 0);

        self::assertCount(1, $results);
        self::assertSame(100000, $results[0]->amountCents);
    }

    public function test_filter_by_counterparty_substring(): void
    {
        $this->tx(counterpartyText: 'ACME Corporation');
        $this->tx(counterpartyText: 'Smith & Sons');
        $this->tx(counterpartyText: 'Acme Ltd');

        $filter = new BankTransactionFilter(counterparty: 'acme');
        $results = $this->repo->findByOrganization(7, $filter, 50, 0);

        self::assertCount(2, $results);
    }

    public function test_empty_filter_returns_all_for_org(): void
    {
        $this->tx(orgId: 7);
        $this->tx(orgId: 7);
        $this->tx(orgId: 999);

        $filter = new BankTransactionFilter();
        self::assertCount(2, $this->repo->findByOrganization(7, $filter, 50, 0));
        self::assertSame(2, $this->repo->countByOrganization(7, $filter));
    }

    public function test_updateStatusById(): void
    {
        $id = $this->tx(status: BankTransactionStatus::Unmatched);
        $this->repo->updateStatusById($id, BankTransactionStatus::Matched);

        $tx = $this->repo->findById(7, $id);
        self::assertSame(BankTransactionStatus::Matched, $tx?->status);
    }

    public function test_voidByBatchId_voids_only_unmatched(): void
    {
        $this->tx(batchId: 1, status: BankTransactionStatus::Unmatched);
        $this->tx(batchId: 1, status: BankTransactionStatus::Unmatched);
        $matchedId = $this->tx(batchId: 1, status: BankTransactionStatus::Matched);

        $this->repo->voidByBatchId(1);

        $filter = new BankTransactionFilter(status: BankTransactionStatus::Voided);
        self::assertSame(2, $this->repo->countByOrganization(7, $filter));

        $matched = $this->repo->findById(7, $matchedId);
        self::assertSame(BankTransactionStatus::Matched, $matched?->status);
    }
}
