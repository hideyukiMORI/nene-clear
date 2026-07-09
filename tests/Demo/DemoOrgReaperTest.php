<?php

declare(strict_types=1);

namespace NeneClear\Tests\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Demo\DemoOrgReaper;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class DemoOrgReaperTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-reaper-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createOrganizations($this->query);
        SchemaFixture::createUsers($this->query);
        SchemaFixture::createUserInvitations($this->query);
        SchemaFixture::createBankAccounts($this->query);
        SchemaFixture::createBankImportBatches($this->query);
        SchemaFixture::createBankTransactions($this->query);
        SchemaFixture::createPaymentReconciliations($this->query);
        SchemaFixture::createReconciliationAllocations($this->query);
        SchemaFixture::createClientCredits($this->query);
        SchemaFixture::createClearSettings($this->query);
        SchemaFixture::createDunningNotices($this->query);
        SchemaFixture::createDunningPauses($this->query);
        SchemaFixture::createManualReceivables($this->query);
        SchemaFixture::createAuditEvents($this->query);
        SchemaFixture::createTotpTables($this->query);
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
    }

    /** Seed a minimal org with one row in every reaped table. */
    private function seedOrg(int $orgId, string $slug): int
    {
        $q = $this->query;
        $q->execute("INSERT INTO organizations (id, slug, name, created_at, is_deleted) VALUES (?, ?, 'Demo', '2026-07-01 00:00:00', 0)", [$orgId, $slug]);
        $userId = $q->insert(
            "INSERT INTO users (organization_id, email, role, status, password_hash, is_deleted) VALUES (?, ?, 'admin', 'active', 'x', 0)",
            [$orgId, 'demo-admin@' . $slug . '.example'],
        );
        $q->execute('INSERT INTO totp_secrets (user_id, secret, is_enabled, failed_attempts, created_at) VALUES (?, ?, 0, 0, ?)', [$userId, 's', '2026-07-01 00:00:00']);
        $q->execute("INSERT INTO clear_settings (organization_id, dunning_min_interval_days, created_at, updated_at) VALUES (?, 7, '2026-07-01', '2026-07-01')", [$orgId]);
        $accountId = $q->insert(
            "INSERT INTO bank_accounts (organization_id, bank_name, bank_branch, account_type, account_number, csv_encoding, csv_date_format, csv_date_column, csv_amount_column, csv_counterparty_column, csv_header_rows, is_deleted) VALUES (?, 'B', 'H', 'ordinary', '1', 'utf8', 'Y/m/d', 0, 1, 3, 1, 0)",
            [$orgId],
        );
        $batchId = $q->insert(
            "INSERT INTO bank_import_batches (organization_id, bank_account_id, file_hash, source_filename, row_count, status, imported_by, imported_at) VALUES (?, ?, ?, 'x.csv', 1, 'imported', ?, '2026-07-01')",
            [$orgId, $accountId, 'h' . $orgId, $userId],
        );
        $txId = $q->insert(
            "INSERT INTO bank_transactions (organization_id, bank_import_batch_id, bank_account_id, value_date, amount_cents, counterparty_text, line_key, status, created_at) VALUES (?, ?, ?, '2026-07-01', 100, 'X', ?, 'matched', '2026-07-01')",
            [$orgId, $batchId, $accountId, 'k' . $orgId],
        );
        $reconId = $q->insert(
            "INSERT INTO payment_reconciliations (organization_id, bank_transaction_id, status, confirmed_by, confirmed_at, created_at, idempotency_key) VALUES (?, ?, 'confirmed', ?, '2026-07-01', '2026-07-01', ?)",
            [$orgId, $txId, $userId, 'idem-' . $orgId],
        );
        $q->execute(
            "INSERT INTO reconciliation_allocations (organization_id, payment_reconciliation_id, invoice_id, amount_cents, created_at, source) VALUES (?, ?, 1, 100, '2026-07-01', 'invoice_upstream')",
            [$orgId, $reconId],
        );
        $q->execute(
            "INSERT INTO client_credits (organization_id, client_id, amount_cents, remaining_cents, status, source_bank_transaction_id, reconciliation_id, created_by, created_at) VALUES (?, 1, 10, 10, 'open', ?, ?, ?, '2026-07-01')",
            [$orgId, $txId, $reconId, $userId],
        );
        $q->execute(
            "INSERT INTO manual_receivables (organization_id, reference_number, client_name, recipient_email, total_cents, outstanding_cents, currency, issued_at, due_at, status, created_by, created_at, updated_at, is_deleted) VALUES (?, ?, 'C', 'a@b.example', 100, 100, 'JPY', '2026-07-01', '2026-07-10', 'open', ?, '2026-07-01', '2026-07-01', 0)",
            [$orgId, 'MR-' . $orgId, $userId],
        );
        $q->execute(
            "INSERT INTO dunning_notices (organization_id, invoice_id, invoice_number, client_id, recipient_email, outstanding_cents, due_at, channel, sent_by, sent_at, created_at, template_version) VALUES (?, 1, 'INV-1', 1, 'a@b.example', 100, '2026-07-01', 'log', ?, '2026-07-01', '2026-07-01', '1.0')",
            [$orgId, $userId],
        );
        $q->execute(
            "INSERT INTO dunning_pauses (organization_id, invoice_id, paused_by, paused_at, paused_reason) VALUES (?, 1, ?, '2026-07-01', 'r')",
            [$orgId, $userId],
        );
        $q->execute(
            "INSERT INTO audit_events (organization_id, action, actor_id, occurred_at, entity_type, entity_id) VALUES (?, 'organization_created', ?, '2026-07-01 00:00:00', 'organization', ?)",
            [$orgId, $userId, $orgId],
        );

        return $userId;
    }

    private function countFor(string $table, int $orgId): int
    {
        $row = $this->query->fetchOne("SELECT COUNT(*) AS n FROM {$table} WHERE organization_id = ?", [$orgId]);

        return is_array($row) ? (int) $row['n'] : -1;
    }

    public function test_reap_removes_the_org_and_every_child_but_leaves_others_untouched(): void
    {
        $this->seedOrg(10, 'demo-aaaa');
        $keptUser = $this->seedOrg(20, 'real-org');

        (new DemoOrgReaper($this->query))->reap(10);

        foreach ([
            'organizations' => 'id', 'users' => 'organization_id', 'clear_settings' => 'organization_id',
            'bank_accounts' => 'organization_id', 'bank_import_batches' => 'organization_id',
            'bank_transactions' => 'organization_id', 'payment_reconciliations' => 'organization_id',
            'reconciliation_allocations' => 'organization_id', 'client_credits' => 'organization_id',
            'manual_receivables' => 'organization_id', 'dunning_notices' => 'organization_id',
            'dunning_pauses' => 'organization_id', 'audit_events' => 'organization_id',
        ] as $table => $column) {
            $row = $this->query->fetchOne("SELECT COUNT(*) AS n FROM {$table} WHERE {$column} = ?", [10]);
            self::assertSame(0, is_array($row) ? (int) $row['n'] : -1, "{$table} not fully reaped");
        }

        // Per-user TOTP rows of the reaped org are gone; the other org's remain.
        $row = $this->query->fetchOne('SELECT COUNT(*) AS n FROM totp_secrets');
        self::assertSame(1, is_array($row) ? (int) $row['n'] : -1);
        $row = $this->query->fetchOne('SELECT COUNT(*) AS n FROM totp_secrets WHERE user_id = ?', [$keptUser]);
        self::assertSame(1, is_array($row) ? (int) $row['n'] : -1);

        self::assertSame(1, $this->countFor('users', 20));
        self::assertSame(1, $this->countFor('dunning_notices', 20));
    }

    public function test_reap_is_idempotent(): void
    {
        $this->seedOrg(10, 'demo-aaaa');
        $reaper = new DemoOrgReaper($this->query);

        $reaper->reap(10);
        $reaper->reap(10); // already gone — success, no throw

        self::assertSame(0, $this->countFor('users', 10));
    }
}
