<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Dunning\DunningNotice;
use NeneClear\Dunning\DunningNoticeFilter;
use NeneClear\Dunning\DunningStage;
use NeneClear\Dunning\PdoDunningNoticeRepository;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoDunningNoticeRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private PdoDunningNoticeRepository $repo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-dun-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createDunningNotices($this->query);
        $this->repo = new PdoDunningNoticeRepository($this->query);

        $this->seed('INV-001', 'a@acme.example', 110000, '2026-04-10 09:00:00', sentBy: 1);
        $this->seed('INV-002', 'b@midori.example', 50000, '2026-04-20 09:00:00', sentBy: 2);
        $this->seed('INV-003', 'a@acme.example', 220000, '2026-04-25 09:00:00', sentBy: 1);
        $this->seed('INV-099', 'x@other.example', 9000, '2026-05-02 09:00:00', sentBy: 1, orgId: 99);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function seed(string $invoiceNumber, string $email, int $outstanding, string $sentAt, int $sentBy, int $orgId = 7, DunningStage $stage = DunningStage::Initial): int
    {
        return $this->repo->save(new DunningNotice(
            organizationId: $orgId,
            invoiceId: 1,
            invoiceNumber: $invoiceNumber,
            clientId: 45,
            recipientEmail: $email,
            outstandingCents: $outstanding,
            dueAt: '2026-04-30',
            channel: 'log',
            templateVersion: '1.0',
            stage: $stage,
            sentBy: $sentBy,
            sentAt: $sentAt,
        ));
    }

    public function test_stage_round_trips_through_the_database(): void
    {
        // The point of #414: which stage went out must survive the write. Before
        // this column the stage only picked a template and was then discarded, so
        // nothing could tell whether a customer had already had a `final` demand.
        $id = $this->seed('INV-500', 'z@acme.example', 70000, '2026-05-10 09:00:00', sentBy: 1, stage: DunningStage::Final);

        $stored = $this->repo->findById(7, $id);

        self::assertNotNull($stored);
        self::assertSame(DunningStage::Final, $stored->stage);
    }

    public function test_text_amount_and_actor_filters(): void
    {
        self::assertCount(1, $this->repo->findByOrganization(7, new DunningNoticeFilter(invoiceNumber: 'INV-002'), 50, 0));
        self::assertCount(2, $this->repo->findByOrganization(7, new DunningNoticeFilter(recipientEmail: 'acme'), 50, 0));
        self::assertCount(1, $this->repo->findByOrganization(7, new DunningNoticeFilter(outstandingMinCents: 200000), 50, 0));
        self::assertCount(2, $this->repo->findByOrganization(7, new DunningNoticeFilter(sentBy: 1), 50, 0));
    }

    public function test_sent_date_range_and_sort(): void
    {
        $range = new DunningNoticeFilter(sentFrom: '2026-04-15', sentTo: '2026-04-30');
        self::assertCount(2, $this->repo->findByOrganization(7, $range, 50, 0));

        $rows = $this->repo->findByOrganization(7, new DunningNoticeFilter(sortColumn: 'outstanding_cents', sortDirection: 'asc'), 50, 0);
        $amounts = array_map(static fn (DunningNotice $n): int => $n->outstandingCents, $rows);
        self::assertSame([50000, 110000, 220000], $amounts);
    }
}
