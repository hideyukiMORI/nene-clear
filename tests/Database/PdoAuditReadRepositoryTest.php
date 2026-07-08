<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventFilter;
use NeneClear\Audit\PdoAuditReadRepository;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

/**
 * Read side of the audit trail on the canonical schema (stage 2, Issue #258).
 *
 * Every row is stored canonically (`before_json` / `after_json` /
 * `metadata_json`), so hydration is a straight column read — the stage-1
 * flat/folded normalization layer is gone. Clear-specific read concerns are
 * covered here: tenant scoping, the `actor_id` sort (absent from the framework
 * `AuditQuery`), and the inclusive `DATE(occurred_at)` bounds.
 */
final class PdoAuditReadRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private PdoAuditReadRepository $repo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-audit-read-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createAuditEvents($this->query);
        $this->repo = new PdoAuditReadRepository($this->query);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function insertRaw(
        string $action,
        string $entityType,
        ?int $entityId,
        int $actor,
        string $at,
        ?string $beforeJson,
        ?string $afterJson,
        ?string $metadataJson = null,
        int $orgId = 7,
    ): void {
        $this->query->execute(
            'INSERT INTO audit_events (organization_id, action, entity_type, entity_id, actor_id, occurred_at, before_json, after_json, metadata_json) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$orgId, $action, $entityType, $entityId, $actor, $at, $beforeJson, $afterJson, $metadataJson],
        );
    }

    private function find(AuditEventFilter $filter): AuditEvent
    {
        $rows = $this->repo->findByOrganization(7, $filter, 50, 0);
        self::assertCount(1, $rows);

        return $rows[0];
    }

    public function test_hydrates_before_after_and_metadata_from_canonical_columns(): void
    {
        $this->insertRaw(
            'dunning_paused',
            'invoice',
            5,
            2,
            '2026-04-20 12:00:00',
            (string) json_encode(['is_paused' => false]),
            (string) json_encode(['is_paused' => true]),
            (string) json_encode(['invoice_id' => 5]),
        );

        $event = $this->find(new AuditEventFilter(action: 'dunning_paused'));

        self::assertSame('dunning_paused', $event->action);
        self::assertSame('invoice', $event->entityType);
        self::assertSame(5, $event->entityId);
        self::assertSame(2, $event->actorId);
        self::assertSame(['is_paused' => false], $event->before);
        self::assertSame(['is_paused' => true], $event->after);
        self::assertSame(['invoice_id' => 5], $event->metadata);
    }

    public function test_null_columns_hydrate_as_null(): void
    {
        $this->insertRaw(
            'user_created',
            'user',
            10,
            1,
            '2026-04-10 09:00:00',
            null,
            (string) json_encode(['email' => 'a@acme.example']),
        );

        $event = $this->find(new AuditEventFilter(action: 'user_created'));

        self::assertNull($event->before);
        self::assertSame(['email' => 'a@acme.example'], $event->after);
        self::assertNull($event->metadata);
    }

    /** `actor_id` sort — a column the framework `AuditQuery` cannot express. */
    public function test_sort_by_actor_id(): void
    {
        $this->insertRaw('user_created', 'user', 1, 9, '2026-04-10 09:00:00', null, '{}');
        $this->insertRaw('user_created', 'user', 2, 3, '2026-04-11 09:00:00', null, '{}');
        $this->insertRaw('user_created', 'user', 3, 6, '2026-04-12 09:00:00', null, '{}');

        $rows = $this->repo->findByOrganization(7, new AuditEventFilter(sortColumn: 'actor_id', sortDirection: 'asc'), 50, 0);
        $actors = array_map(static fn (AuditEvent $e): int => $e->actorId, $rows);
        self::assertSame([3, 6, 9], $actors);
    }

    public function test_tenant_scoping_and_filters(): void
    {
        $this->insertRaw('user_created', 'user', 10, 1, '2026-04-10 09:00:00', null, '{}');
        $this->insertRaw('login_failed', 'user', null, 0, '2026-04-25 23:00:00', null, '{}');
        $this->insertRaw('user_created', 'user', 11, 1, '2026-05-02 09:00:00', null, '{}', null, 99);

        self::assertCount(2, $this->repo->findByOrganization(7, new AuditEventFilter(), 50, 0));
        self::assertSame(2, $this->repo->countByOrganization(7, new AuditEventFilter()));
        self::assertCount(1, $this->repo->findByOrganization(7, new AuditEventFilter(action: 'user_created'), 50, 0));
        self::assertCount(1, $this->repo->findByOrganization(7, new AuditEventFilter(actorId: 1), 50, 0));

        $range = new AuditEventFilter(occurredFrom: '2026-04-20', occurredTo: '2026-04-30');
        self::assertCount(1, $this->repo->findByOrganization(7, $range, 50, 0));
    }
}
