<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventFilter;
use NeneClear\Audit\PdoAuditEventRepository;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoAuditEventRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private PdoAuditEventRepository $repo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-audit-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createAuditEvents($this->query);
        $this->repo = new PdoAuditEventRepository($this->query);

        $this->seed('user_created', 'user', 10, actor: 1, at: '2026-04-10 09:00:00');
        $this->seed('reconciliation_confirmed', 'payment_reconciliation', 5, actor: 2, at: '2026-04-20 12:00:00');
        $this->seed('login_failed', 'user', null, actor: 0, at: '2026-04-25 23:00:00');
        $this->seed('user_created', 'user', 11, actor: 1, at: '2026-05-02 09:00:00', orgId: 99);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function seed(string $eventType, string $entityType, ?int $entityId, int $actor, string $at, int $orgId = 7): void
    {
        $this->repo->record(new AuditEvent(
            organizationId: $orgId,
            eventType: $eventType,
            entityType: $entityType,
            entityId: $entityId,
            actorUserId: $actor,
            occurredAt: $at,
            payload: ['after' => ['x' => 1]],
        ));
    }

    public function test_tenant_scoping_and_no_filter(): void
    {
        self::assertCount(3, $this->repo->findByOrganization(7, new AuditEventFilter(), 50, 0));
        self::assertSame(3, $this->repo->countByOrganization(7, new AuditEventFilter()));
    }

    public function test_event_type_actor_and_date_filters(): void
    {
        self::assertCount(1, $this->repo->findByOrganization(7, new AuditEventFilter(eventType: 'user_created'), 50, 0));
        // actor 1 has two events but one belongs to org 99 → only one is in org 7.
        self::assertCount(1, $this->repo->findByOrganization(7, new AuditEventFilter(actorUserId: 1), 50, 0));

        $range = new AuditEventFilter(occurredFrom: '2026-04-15', occurredTo: '2026-04-30');
        self::assertCount(2, $this->repo->findByOrganization(7, $range, 50, 0)); // 04-20 and 04-25
    }

    public function test_sort_by_occurred_at_ascending(): void
    {
        $rows = $this->repo->findByOrganization(7, new AuditEventFilter(sortColumn: 'occurred_at', sortDirection: 'asc'), 50, 0);
        $times = array_map(static fn (AuditEvent $e): string => $e->occurredAt, $rows);
        self::assertSame(['2026-04-10 09:00:00', '2026-04-20 12:00:00', '2026-04-25 23:00:00'], $times);
    }
}
