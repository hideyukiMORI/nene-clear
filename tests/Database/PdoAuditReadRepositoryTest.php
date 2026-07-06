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
 * Read side of the audit trail after the framework recorder adoption (stage ①).
 *
 * The framework writes new rows in SinglePayload *folded* shape
 * (`{before, after, metadata:{…}}`), while historical rows are *flat*
 * (`{…context, before, after}`). This proves both shapes read back as the same
 * flat API payload, and that Clear's `actor_user_id` sort (absent from the
 * framework's `AuditQuery`) still works.
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
        string $eventType,
        string $entityType,
        ?int $entityId,
        int $actor,
        string $at,
        string $payloadJson,
        int $orgId = 7,
    ): void {
        $this->query->execute(
            'INSERT INTO audit_events (organization_id, event_type, entity_type, entity_id, actor_user_id, occurred_at, payload_json) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$orgId, $eventType, $entityType, $entityId, $actor, $at, $payloadJson],
        );
    }

    private function find(AuditEventFilter $filter): AuditEvent
    {
        $rows = $this->repo->findByOrganization(7, $filter, 50, 0);
        self::assertCount(1, $rows);

        return $rows[0];
    }

    /**
     * A legacy flat row (context key alongside before/after) is returned verbatim:
     * the context key stays at the top level for the frontend's context panel.
     */
    public function test_legacy_flat_row_is_returned_verbatim(): void
    {
        $this->insertRaw(
            'dunning_paused',
            'invoice',
            5,
            2,
            '2026-04-20 12:00:00',
            (string) json_encode(['invoice_id' => 5, 'before' => ['is_paused' => false], 'after' => ['is_paused' => true]]),
        );

        $event = $this->find(new AuditEventFilter(eventType: 'dunning_paused'));

        self::assertSame(
            ['invoice_id' => 5, 'before' => ['is_paused' => false], 'after' => ['is_paused' => true]],
            $event->payload,
        );
    }

    /**
     * A framework-folded row is normalized back to the flat shape: the metadata
     * object's keys are lifted to the top level and the `metadata` wrapper is
     * dropped, so it is indistinguishable from a legacy flat row.
     */
    public function test_folded_row_is_normalized_to_flat_shape(): void
    {
        $this->insertRaw(
            'dunning_resumed',
            'invoice',
            5,
            1,
            '2026-04-10 09:00:00',
            (string) json_encode(['before' => ['is_paused' => true], 'after' => ['is_paused' => false], 'metadata' => ['invoice_id' => 5]]),
        );

        $event = $this->find(new AuditEventFilter(eventType: 'dunning_resumed'));

        self::assertArrayNotHasKey('metadata', $event->payload);
        self::assertSame(5, $event->payload['invoice_id']);
        self::assertSame(['is_paused' => true], $event->payload['before']);
        self::assertSame(['is_paused' => false], $event->payload['after']);
    }

    /**
     * The whole point of stage ①: a folded row and a flat row carrying the same
     * logical content expose an identical payload to the API (same context key at
     * the top level, same before/after, no `metadata` wrapper on either).
     */
    public function test_folded_and_flat_rows_expose_identical_payload_shape(): void
    {
        $flat = ['invoice_id' => 9, 'before' => ['is_paused' => false], 'after' => ['is_paused' => true]];
        $this->insertRaw('dunning_paused', 'invoice', 9, 3, '2026-05-01 08:00:00', (string) json_encode($flat));
        $this->insertRaw(
            'dunning_paused',
            'invoice',
            9,
            3,
            '2026-05-02 08:00:00',
            (string) json_encode(['before' => ['is_paused' => false], 'after' => ['is_paused' => true], 'metadata' => ['invoice_id' => 9]]),
        );

        $rows = $this->repo->findByOrganization(7, new AuditEventFilter(eventType: 'dunning_paused'), 50, 0);
        self::assertCount(2, $rows);

        // Both rows normalize to the same flat payload regardless of stored shape.
        self::assertSame($rows[0]->payload, $rows[1]->payload);
        self::assertSame($flat, $rows[0]->payload);
    }

    /** `actor_user_id` sort — a column the framework `AuditQuery` cannot express. */
    public function test_sort_by_actor_user_id(): void
    {
        $this->insertRaw('user_created', 'user', 1, 9, '2026-04-10 09:00:00', (string) json_encode(['after' => []]));
        $this->insertRaw('user_created', 'user', 2, 3, '2026-04-11 09:00:00', (string) json_encode(['after' => []]));
        $this->insertRaw('user_created', 'user', 3, 6, '2026-04-12 09:00:00', (string) json_encode(['after' => []]));

        $rows = $this->repo->findByOrganization(7, new AuditEventFilter(sortColumn: 'actor_user_id', sortDirection: 'asc'), 50, 0);
        $actors = array_map(static fn (AuditEvent $e): int => $e->actorUserId, $rows);
        self::assertSame([3, 6, 9], $actors);
    }

    public function test_tenant_scoping_and_filters(): void
    {
        $this->insertRaw('user_created', 'user', 10, 1, '2026-04-10 09:00:00', (string) json_encode(['after' => []]));
        $this->insertRaw('login_failed', 'user', null, 0, '2026-04-25 23:00:00', (string) json_encode(['after' => []]));
        $this->insertRaw('user_created', 'user', 11, 1, '2026-05-02 09:00:00', (string) json_encode(['after' => []]), 99);

        self::assertCount(2, $this->repo->findByOrganization(7, new AuditEventFilter(), 50, 0));
        self::assertSame(2, $this->repo->countByOrganization(7, new AuditEventFilter()));
        self::assertCount(1, $this->repo->findByOrganization(7, new AuditEventFilter(eventType: 'user_created'), 50, 0));
        self::assertCount(1, $this->repo->findByOrganization(7, new AuditEventFilter(actorUserId: 1), 50, 0));

        $range = new AuditEventFilter(occurredFrom: '2026-04-20', occurredTo: '2026-04-30');
        self::assertCount(1, $this->repo->findByOrganization(7, $range, 50, 0));
    }
}
