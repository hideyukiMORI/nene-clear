<?php

declare(strict_types=1);

use Phinx\Db\Adapter\PdoAdapter;
use Phinx\Db\Adapter\WrapperInterface;
use Phinx\Migration\AbstractMigration;

/**
 * Canonicalizes the audit trail to the framework-canonical table shape
 * (`Nene2\Audit\AuditTableConfig::canonical()`, NENE2 ADR 0014) — stage 2 of
 * the audit framework adoption (Issue #258; stage 1 was #254 / PR #255).
 *
 * 1. Splits `payload_json` into the canonical `before_json` / `after_json` /
 *    `metadata_json` columns. Both stored shapes are converted losslessly:
 *    - legacy *flat* rows `{…context, before, after}` — context keys move to
 *      `metadata_json`;
 *    - stage-1 *folded* rows `{before, after, metadata:{…}}` — direct mapping.
 *    Every payload key lands in exactly one of the three columns, so no audit
 *    information is dropped. The append-only contract is respected: this is a
 *    format normalization only; the recorded content is unchanged.
 * 2. Drops `payload_json` once every row is converted.
 * 3. Renames `event_type` → `action` and `actor_user_id` → `actor_id`.
 *
 * Every step is guarded (hasColumn), so a partially-applied run is re-run safe.
 * A row whose payload is not a JSON object aborts the migration loudly rather
 * than risking silent trail corruption.
 */
final class CanonicalizeAuditEvents extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->table('audit_events')->hasColumn('before_json')) {
            $this->table('audit_events')
                ->addColumn('before_json', 'text', ['null' => true, 'default' => null])
                ->addColumn('after_json', 'text', ['null' => true, 'default' => null])
                ->addColumn('metadata_json', 'text', ['null' => true, 'default' => null])
                ->update();
        }

        if ($this->table('audit_events')->hasColumn('payload_json')) {
            $this->backfill();
            $this->table('audit_events')->removeColumn('payload_json')->update();
        }

        if ($this->table('audit_events')->hasColumn('event_type')) {
            $this->table('audit_events')->renameColumn('event_type', 'action')->update();
        }

        if ($this->table('audit_events')->hasColumn('actor_user_id')) {
            $this->table('audit_events')->renameColumn('actor_user_id', 'actor_id')->update();
        }
    }

    public function down(): void
    {
        if ($this->table('audit_events')->hasColumn('action')) {
            $this->table('audit_events')->renameColumn('action', 'event_type')->update();
        }

        if ($this->table('audit_events')->hasColumn('actor_id')) {
            $this->table('audit_events')->renameColumn('actor_id', 'actor_user_id')->update();
        }

        if (!$this->table('audit_events')->hasColumn('payload_json')) {
            $this->table('audit_events')
                ->addColumn('payload_json', 'text', ['null' => true, 'default' => null])
                ->update();
            $this->refold();
            $this->table('audit_events')
                ->changeColumn('payload_json', 'text', ['null' => false])
                ->update();
        }

        if ($this->table('audit_events')->hasColumn('before_json')) {
            $this->table('audit_events')
                ->removeColumn('before_json')
                ->removeColumn('after_json')
                ->removeColumn('metadata_json')
                ->update();
        }
    }

    /** Converts every `payload_json` row into the three canonical columns. */
    private function backfill(): void
    {
        $pdo = $this->pdo();

        /** @var list<array{id: int|string, payload_json: string|null}> $rows */
        $rows = $pdo->query('SELECT id, payload_json FROM audit_events')->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare(
            'UPDATE audit_events SET before_json = ?, after_json = ?, metadata_json = ? WHERE id = ?',
        );

        foreach ($rows as $row) {
            [$before, $after, $metadata] = self::split((string) $row['payload_json'], (int) $row['id']);
            $update->execute([$before, $after, $metadata, $row['id']]);
        }
    }

    /** Folds the three canonical columns back into the stage-1 folded payload. */
    private function refold(): void
    {
        $pdo = $this->pdo();

        /** @var list<array<string, mixed>> $rows */
        $rows = $pdo
            ->query('SELECT id, before_json, after_json, metadata_json FROM audit_events')
            ->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE audit_events SET payload_json = ? WHERE id = ?');

        foreach ($rows as $row) {
            $folded = [];
            foreach (['before' => 'before_json', 'after' => 'after_json', 'metadata' => 'metadata_json'] as $key => $column) {
                if (is_string($row[$column]) && $row[$column] !== '') {
                    $folded[$key] = json_decode($row[$column], true, 512, JSON_THROW_ON_ERROR);
                }
            }
            $update->execute([
                $folded === [] ? '{}' : json_encode($folded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $row['id'],
            ]);
        }
    }

    /**
     * Splits one stored payload into `[before, after, metadata]` JSON strings.
     *
     * Mirrors the read-side normalization this migration retires: a folded
     * row's `metadata` object is lifted to the top level first, then `before` /
     * `after` snapshots are extracted, and every remaining top-level key —
     * legacy context such as `invoice_id` or `email` — becomes metadata.
     *
     * @return array{?string, ?string, ?string}
     */
    private static function split(string $payloadJson, int $id): array
    {
        try {
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('audit_events.id=%d: payload_json is not valid JSON.', $id), 0, $e);
        }

        if (!is_array($payload)) {
            throw new RuntimeException(sprintf('audit_events.id=%d: payload_json is not a JSON object.', $id));
        }

        if (isset($payload['metadata']) && is_array($payload['metadata'])) {
            /** @var array<string, mixed> $folded */
            $folded = $payload['metadata'];
            unset($payload['metadata']);
            $payload = array_merge($folded, $payload);
        }

        $before = null;
        if (array_key_exists('before', $payload) && is_array($payload['before'])) {
            $before = $payload['before'];
            unset($payload['before']);
        }

        $after = null;
        if (array_key_exists('after', $payload) && is_array($payload['after'])) {
            $after = $payload['after'];
            unset($payload['after']);
        }

        return [self::encode($before), self::encode($after), self::encode($payload === [] ? null : $payload)];
    }

    /**
     * @param array<mixed>|null $value
     */
    private static function encode(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** The raw PDO connection, for parameterised backfill statements. */
    private function pdo(): PDO
    {
        $adapter = $this->getAdapter();

        // The CLI runner hands migrations a decorated adapter (timed output);
        // unwrap to the real PDO-backed adapter.
        while ($adapter instanceof WrapperInterface) {
            $adapter = $adapter->getAdapter();
        }

        if (!$adapter instanceof PdoAdapter) {
            throw new RuntimeException('The audit canonicalization backfill requires a PDO adapter.');
        }

        $connection = $adapter->getConnection();

        if (!$connection instanceof PDO) {
            throw new RuntimeException('The PDO adapter returned no PDO connection.');
        }

        return $connection;
    }
}
