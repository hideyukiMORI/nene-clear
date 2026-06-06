<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Reconciliation\ClientCredit;
use NeneClear\Reconciliation\ClientCreditFilter;
use NeneClear\Reconciliation\ClientCreditStatus;
use NeneClear\Reconciliation\PdoClientCreditRepository;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoClientCreditRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private PdoClientCreditRepository $repo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-credit-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createClientCredits($this->query);
        $this->repo = new PdoClientCreditRepository($this->query);

        // org 7 fixtures (one belongs to another org to prove tenant scoping)
        $this->seed(clientId: 45, amount: 50000, remaining: 50000, status: ClientCreditStatus::Open, createdAt: '2026-04-10 09:00:00');
        $this->seed(clientId: 88, amount: 12000, remaining: 4000, status: ClientCreditStatus::Open, createdAt: '2026-04-20 09:00:00');
        $this->seed(clientId: 45, amount: 99000, remaining: 0, status: ClientCreditStatus::Voided, createdAt: '2026-04-25 09:00:00');
        $this->seed(clientId: 45, amount: 30000, remaining: 30000, status: ClientCreditStatus::Open, createdAt: '2026-05-02 09:00:00', orgId: 99);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function seed(int $clientId, int $amount, int $remaining, ClientCreditStatus $status, string $createdAt, int $orgId = 7): void
    {
        $this->repo->save(new ClientCredit(
            organizationId: $orgId,
            clientId: $clientId,
            amountCents: $amount,
            remainingCents: $remaining,
            status: $status,
            sourceBankTransactionId: 1,
            reconciliationId: 1,
            createdBy: 1,
            createdAt: $createdAt,
        ));
    }

    public function test_no_filter_returns_org_rows_only(): void
    {
        $all = $this->repo->findByOrganization(7, new ClientCreditFilter(), 50, 0);
        self::assertCount(3, $all);
        self::assertSame(3, $this->repo->countByOrganization(7, new ClientCreditFilter()));
    }

    public function test_status_and_client_filters(): void
    {
        $open = $this->repo->findByOrganization(7, new ClientCreditFilter(status: ClientCreditStatus::Open), 50, 0);
        self::assertCount(2, $open);

        $client45 = $this->repo->findByOrganization(7, new ClientCreditFilter(clientId: 45), 50, 0);
        self::assertCount(2, $client45);
        self::assertSame(2, $this->repo->countByOrganization(7, new ClientCreditFilter(clientId: 45)));
    }

    public function test_amount_and_remaining_ranges(): void
    {
        $byAmount = $this->repo->findByOrganization(7, new ClientCreditFilter(amountMinCents: 40000), 50, 0);
        self::assertCount(2, $byAmount); // 50000 + 99000

        $withRemaining = $this->repo->findByOrganization(7, new ClientCreditFilter(remainingMinCents: 1), 50, 0);
        self::assertCount(2, $withRemaining); // 50000 + 4000 (the voided one has 0)
    }

    public function test_created_date_range(): void
    {
        $f = new ClientCreditFilter(createdFrom: '2026-04-15', createdTo: '2026-04-30');
        $rows = $this->repo->findByOrganization(7, $f, 50, 0);
        self::assertCount(2, $rows); // 04-20 and 04-25
    }

    public function test_sort_by_amount_ascending(): void
    {
        $rows = $this->repo->findByOrganization(7, new ClientCreditFilter(sortColumn: 'amount_cents', sortDirection: 'asc'), 50, 0);
        $amounts = array_map(static fn (ClientCredit $c): int => $c->amountCents, $rows);
        self::assertSame([12000, 50000, 99000], $amounts);
    }
}
