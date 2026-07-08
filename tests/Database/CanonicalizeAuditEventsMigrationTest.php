<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use PDO;
use Phinx\Db\Adapter\SQLiteAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Backfill coverage for the stage-2 audit canonicalization migration
 * (Issue #258): the pre-canonical `audit_events` table (with `payload_json`)
 * is converted in place, and **every** stored payload shape lands losslessly
 * in the canonical `before_json` / `after_json` / `metadata_json` columns.
 *
 * Inputs mirror the real rows observed in the dev database plus the stage-1
 * folded shape:
 * - flat, after-only (`login_succeeded`, `login_failed`)
 * - flat, before-only (`user_deleted`)
 * - flat with a context key alongside before/after (`dunning_resumed` + `invoice_id`)
 * - flat before+after with no context (`invitation_accepted`)
 * - stage-1 folded `{before, after, metadata:{…}}` (`dunning_paused`)
 * - stage-1 folded without metadata (`clear_settings_updated`)
 */
final class CanonicalizeAuditEventsMigrationTest extends TestCase
{
    private const int VERSION = 20260708120000;

    private string $dbPath;
    private SQLiteAdapter $adapter;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../database/migrations/20260530130300_create_audit_events_table.php';
        require_once __DIR__ . '/../../database/migrations/20260606120000_add_entity_to_audit_events.php';
        require_once __DIR__ . '/../../database/migrations/20260708120000_canonicalize_audit_events.php';

        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-audit-migration-', true) . '.sqlite';
        $this->adapter = new SQLiteAdapter(['name' => $this->dbPath, 'suffix' => '']);

        // Build the pre-canonical schema by running the real original
        // migrations, so the table's stored DDL matches what a production
        // database actually carries (Phinx-quoted identifiers — the SQLite
        // copy-alter path depends on them).
        foreach ([new \CreateAuditEventsTable('testing', 20260530130300), new \AddEntityToAuditEvents('testing', 20260606120000)] as $migration) {
            $migration->setAdapter($this->adapter);
            $migration->change();
        }
    }

    protected function tearDown(): void
    {
        $this->adapter->disconnect();

        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function pdo(): PDO
    {
        $connection = $this->adapter->getConnection();
        self::assertInstanceOf(PDO::class, $connection);

        return $connection;
    }

    private function query(string $sql): \PDOStatement
    {
        $statement = $this->pdo()->query($sql);
        self::assertNotFalse($statement);

        return $statement;
    }

    private function migration(): \CanonicalizeAuditEvents
    {
        $migration = new \CanonicalizeAuditEvents('testing', self::VERSION);
        $migration->setAdapter($this->adapter);

        return $migration;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertLegacy(string $eventType, array $payload, int $actor = 1, ?int $entityId = null): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO audit_events (organization_id, event_type, entity_type, entity_id, actor_user_id, occurred_at, payload_json) '
            . 'VALUES (7, ?, \'subject\', ?, ?, \'2026-07-01 09:00:00\', ?)',
        );
        $statement->execute([$eventType, $entityId, $actor, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @return array<string, array{before: ?string, after: ?string, metadata: ?string}>
     */
    private function convertedByAction(): array
    {
        $rows = $this->query('SELECT action, before_json, after_json, metadata_json FROM audit_events ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);

        $byAction = [];
        foreach ($rows as $row) {
            $byAction[(string) $row['action']] = [
                'before' => $row['before_json'],
                'after' => $row['after_json'],
                'metadata' => $row['metadata_json'],
            ];
        }

        return $byAction;
    }

    public function test_backfill_converts_every_stored_payload_shape_losslessly(): void
    {
        $this->insertLegacy('login_succeeded', ['after' => ['user_id' => 20, 'email' => 'tanaka@demo.example']]);
        $this->insertLegacy('login_failed', ['after' => ['email' => 'admin@nene-clear.dev', 'failure_reason' => 'invalid_credentials']], 0);
        $this->insertLegacy('user_deleted', ['before' => ['user_id' => 38, 'email' => 'gone@dev.local', 'role' => 'member']], 1, 38);
        $this->insertLegacy('dunning_resumed', ['invoice_id' => 2104, 'before' => ['is_paused' => true], 'after' => ['is_paused' => false]], 1, 2104);
        $this->insertLegacy('invitation_accepted', ['before' => ['status' => 'invited'], 'after' => ['status' => 'active']], 40, 40);
        // Stage-1 folded rows (framework SinglePayload shape).
        $this->insertLegacy('dunning_paused', ['before' => ['is_paused' => false], 'after' => ['is_paused' => true], 'metadata' => ['invoice_id' => 2104]], 1, 2104);
        $this->insertLegacy('clear_settings_updated', ['before' => ['dunning_min_interval_days' => 14], 'after' => ['dunning_min_interval_days' => 7]], 1, 7);

        $this->migration()->up();

        // Schema is canonical: renamed columns exist, payload_json is gone.
        self::assertTrue($this->adapter->hasColumn('audit_events', 'action'));
        self::assertTrue($this->adapter->hasColumn('audit_events', 'actor_id'));
        self::assertTrue($this->adapter->hasColumn('audit_events', 'before_json'));
        self::assertTrue($this->adapter->hasColumn('audit_events', 'after_json'));
        self::assertTrue($this->adapter->hasColumn('audit_events', 'metadata_json'));
        self::assertFalse($this->adapter->hasColumn('audit_events', 'payload_json'));
        self::assertFalse($this->adapter->hasColumn('audit_events', 'event_type'));
        self::assertFalse($this->adapter->hasColumn('audit_events', 'actor_user_id'));

        $converted = $this->convertedByAction();
        self::assertCount(7, $converted, 'every inserted row survives the migration');

        // Flat after-only: snapshot moves, nothing else appears.
        self::assertNull($converted['login_succeeded']['before']);
        self::assertSame(['user_id' => 20, 'email' => 'tanaka@demo.example'], self::decode($converted['login_succeeded']['after']));
        self::assertNull($converted['login_succeeded']['metadata']);

        // Flat before-only.
        self::assertSame(['user_id' => 38, 'email' => 'gone@dev.local', 'role' => 'member'], self::decode($converted['user_deleted']['before']));
        self::assertNull($converted['user_deleted']['after']);
        self::assertNull($converted['user_deleted']['metadata']);

        // Flat with a context key: the context lands in metadata — no information lost.
        self::assertSame(['is_paused' => true], self::decode($converted['dunning_resumed']['before']));
        self::assertSame(['is_paused' => false], self::decode($converted['dunning_resumed']['after']));
        self::assertSame(['invoice_id' => 2104], self::decode($converted['dunning_resumed']['metadata']));

        // Flat before+after with no context: metadata stays null.
        self::assertSame(['status' => 'invited'], self::decode($converted['invitation_accepted']['before']));
        self::assertSame(['status' => 'active'], self::decode($converted['invitation_accepted']['after']));
        self::assertNull($converted['invitation_accepted']['metadata']);

        // Stage-1 folded: direct mapping, metadata preserved.
        self::assertSame(['is_paused' => false], self::decode($converted['dunning_paused']['before']));
        self::assertSame(['is_paused' => true], self::decode($converted['dunning_paused']['after']));
        self::assertSame(['invoice_id' => 2104], self::decode($converted['dunning_paused']['metadata']));

        // Stage-1 folded without metadata.
        self::assertSame(['dunning_min_interval_days' => 14], self::decode($converted['clear_settings_updated']['before']));
        self::assertNull($converted['clear_settings_updated']['metadata']);

        // No row is left unconverted: every row has at least one canonical
        // payload column populated (all inputs carried a snapshot).
        $unconverted = $this->query('SELECT COUNT(*) FROM audit_events WHERE before_json IS NULL AND after_json IS NULL AND metadata_json IS NULL')
            ->fetchColumn();
        self::assertSame(0, (int) $unconverted, 'backfill must convert every row');

        // Non-payload columns are untouched by the backfill.
        $row = $this->query("SELECT organization_id, entity_type, entity_id, actor_id, occurred_at FROM audit_events WHERE action = 'dunning_resumed'")
            ->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(7, (int) $row['organization_id']);
        self::assertSame('subject', $row['entity_type']);
        self::assertSame(2104, (int) $row['entity_id']);
        self::assertSame(1, (int) $row['actor_id']);
        self::assertSame('2026-07-01 09:00:00', $row['occurred_at']);
    }

    public function test_up_is_rerun_safe_after_completion(): void
    {
        $this->insertLegacy('login_succeeded', ['after' => ['user_id' => 1]]);

        $this->migration()->up();
        // A second run must be a no-op (every step is hasColumn-guarded).
        $this->migration()->up();

        self::assertTrue($this->adapter->hasColumn('audit_events', 'action'));
        self::assertFalse($this->adapter->hasColumn('audit_events', 'payload_json'));
        self::assertSame(
            ['user_id' => 1],
            self::decode((string) $this->query('SELECT after_json FROM audit_events')->fetchColumn()),
        );
    }

    public function test_down_restores_the_stage1_shape(): void
    {
        $this->insertLegacy('dunning_resumed', ['invoice_id' => 9, 'before' => ['is_paused' => true], 'after' => ['is_paused' => false]]);

        $this->migration()->up();
        $this->migration()->down();

        self::assertTrue($this->adapter->hasColumn('audit_events', 'event_type'));
        self::assertTrue($this->adapter->hasColumn('audit_events', 'actor_user_id'));
        self::assertTrue($this->adapter->hasColumn('audit_events', 'payload_json'));
        self::assertFalse($this->adapter->hasColumn('audit_events', 'before_json'));

        // Content survives the round trip in the stage-1 folded shape.
        self::assertSame(
            ['before' => ['is_paused' => true], 'after' => ['is_paused' => false], 'metadata' => ['invoice_id' => 9]],
            self::decode((string) $this->query('SELECT payload_json FROM audit_events')->fetchColumn()),
        );
    }

    public function test_backfill_aborts_loudly_on_a_non_object_payload(): void
    {
        $this->pdo()->exec(
            'INSERT INTO audit_events (organization_id, event_type, entity_type, entity_id, actor_user_id, occurred_at, payload_json) '
            . "VALUES (7, 'login_succeeded', 'user', NULL, 1, '2026-07-01 09:00:00', '\"scalar\"')",
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('payload_json is not a JSON object');

        $this->migration()->up();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
