<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Receivable\ManualReceivable;
use NeneClear\Receivable\ManualReceivableFilter;
use NeneClear\Receivable\ManualReceivableStatus;
use NeneClear\Receivable\PdoManualReceivableRepository;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoManualReceivableRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private PdoManualReceivableRepository $repo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-mrcv-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createManualReceivables($this->query);
        $this->repo = new PdoManualReceivableRepository($this->query);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function seed(
        string $referenceNumber,
        int $orgId = 7,
        ?string $recipientEmail = 'ar@acme.example',
        int $total = 110000,
        ?int $outstanding = null,
        ManualReceivableStatus $status = ManualReceivableStatus::Open,
        ?string $dueAt = '2026-04-30',
    ): int {
        return $this->repo->save(new ManualReceivable(
            organizationId: $orgId,
            referenceNumber: $referenceNumber,
            clientName: 'カ）アクメ',
            recipientEmail: $recipientEmail,
            totalCents: $total,
            outstandingCents: $outstanding ?? $total,
            currency: 'JPY',
            issuedAt: '2026-03-31',
            dueAt: $dueAt,
            status: $status,
            createdBy: 1,
            createdAt: '2026-04-01 09:00:00',
        ));
    }

    public function test_save_returns_id_and_find_by_id_round_trips_all_fields(): void
    {
        $id = $this->seed('INV-2026-001', recipientEmail: null, total: 110000, outstanding: 40000, status: ManualReceivableStatus::PartiallyPaid);

        $found = $this->repo->findById($id);

        self::assertNotNull($found);
        self::assertSame($id, $found->id);
        self::assertSame(7, $found->organizationId);
        self::assertSame('INV-2026-001', $found->referenceNumber);
        self::assertSame('カ）アクメ', $found->clientName);
        self::assertNull($found->recipientEmail);
        self::assertSame(110000, $found->totalCents);
        self::assertSame(40000, $found->outstandingCents);
        self::assertSame('JPY', $found->currency);
        self::assertSame('2026-03-31', $found->issuedAt);
        self::assertSame('2026-04-30', $found->dueAt);
        self::assertSame(ManualReceivableStatus::PartiallyPaid, $found->status);
        self::assertSame('2026-04-01 09:00:00', $found->createdAt);
        self::assertSame('2026-04-01 09:00:00', $found->updatedAt);
    }

    public function test_find_by_organization_returns_only_that_tenant_newest_first(): void
    {
        $this->seed('INV-A');
        $this->seed('INV-B');
        $this->seed('OTHER-ORG', orgId: 99);

        $rows = $this->repo->findByOrganization(7, new ManualReceivableFilter(), 50, 0);

        self::assertCount(2, $rows);
        self::assertSame(2, $this->repo->countByOrganization(7, new ManualReceivableFilter()));
        // newest (highest id) first
        self::assertSame('INV-B', $rows[0]->referenceNumber);
        self::assertSame('INV-A', $rows[1]->referenceNumber);
    }

    public function test_find_by_reference_number_is_tenant_scoped(): void
    {
        $this->seed('DUP-1', orgId: 7);
        $this->seed('DUP-1', orgId: 99);

        $found = $this->repo->findByReferenceNumber(7, 'DUP-1');
        self::assertNotNull($found);
        self::assertSame(7, $found->organizationId);

        self::assertNull($this->repo->findByReferenceNumber(7, 'NOPE'));
    }

    public function test_soft_delete_hides_the_row_from_all_reads(): void
    {
        $id = $this->seed('INV-DEL');

        $this->repo->softDelete($id, '2026-05-01 10:00:00');

        self::assertNull($this->repo->findById($id));
        self::assertNull($this->repo->findByReferenceNumber(7, 'INV-DEL'));
        self::assertCount(0, $this->repo->findByOrganization(7, new ManualReceivableFilter(), 50, 0));
    }

    public function test_soft_deleted_reference_number_can_be_reused(): void
    {
        $first = $this->seed('INV-REUSE');
        $this->repo->softDelete($first, '2026-05-01 10:00:00');

        // Re-entering the same number after a soft-delete is allowed: the
        // dedupe key only considers non-deleted rows.
        $second = $this->seed('INV-REUSE');

        $found = $this->repo->findByReferenceNumber(7, 'INV-REUSE');
        self::assertNotNull($found);
        self::assertSame($second, $found->id);
    }
}
