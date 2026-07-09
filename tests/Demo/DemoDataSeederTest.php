<?php

declare(strict_types=1);

namespace NeneClear\Tests\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Demo\DemoDataSeeder;
use NeneClear\Demo\DemoTemplate;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class DemoDataSeederTest extends TestCase
{
    private const int ORG = 42;

    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-demo-seed-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createOrganizations($this->query);
        SchemaFixture::createUsers($this->query);
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

        $this->query->execute(
            "INSERT INTO organizations (id, slug, name, created_at, is_deleted) VALUES (?, 'demo-test', 'Demo', '2026-07-10 00:00:00', 0)",
            [self::ORG],
        );
        $this->query->execute(
            "INSERT INTO users (organization_id, email, role, status, password_hash, is_deleted) VALUES (?, 'demo-admin@demo-test.example', 'admin', 'active', 'x', 0)",
            [self::ORG],
        );

        (new DemoDataSeeder($this->query, new FixedClock('2026-07-10T09:00:00+00:00')))
            ->seed(self::ORG, DemoTemplate::Standard);
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
    }

    /** @param list<string|int> $params */
    private function countRows(string $sql, array $params = []): int
    {
        $row = $this->query->fetchOne($sql, array_merge([self::ORG], $params));

        return is_array($row) ? (int) array_values($row)[0] : -1;
    }

    public function test_seeds_a_compact_but_complete_dataset(): void
    {
        self::assertGreaterThan(50, $this->countRows('SELECT COUNT(*) FROM bank_transactions WHERE organization_id = ?'));
        self::assertSame(7, $this->countRows('SELECT COUNT(*) FROM manual_receivables WHERE organization_id = ?'));
        self::assertGreaterThan(20, $this->countRows('SELECT COUNT(*) FROM payment_reconciliations WHERE organization_id = ?'));
        self::assertSame(6, $this->countRows('SELECT COUNT(*) FROM dunning_notices WHERE organization_id = ?'));
        self::assertSame(1, $this->countRows('SELECT COUNT(*) FROM dunning_pauses WHERE organization_id = ?'));
        self::assertGreaterThan(30, $this->countRows('SELECT COUNT(*) FROM audit_events WHERE organization_id = ?'));
    }

    public function test_every_confirmed_reconciliation_has_exactly_one_allocation(): void
    {
        $orphans = $this->countRows(
            'SELECT COUNT(*) FROM payment_reconciliations pr WHERE pr.organization_id = ? AND'
            . ' (SELECT COUNT(*) FROM reconciliation_allocations ra WHERE ra.payment_reconciliation_id = pr.id) != 1',
        );
        self::assertSame(0, $orphans);
    }

    public function test_settled_manual_receivables_balance_with_their_allocations(): void
    {
        $inconsistent = $this->countRows(
            'SELECT COUNT(*) FROM manual_receivables mr WHERE mr.organization_id = ? AND mr.total_cents - '
            . ' COALESCE((SELECT SUM(ra.amount_cents) FROM reconciliation_allocations ra WHERE ra.manual_receivable_id = mr.id), 0)'
            . ' != mr.outstanding_cents',
        );
        self::assertSame(0, $inconsistent);
    }

    public function test_the_name_mismatch_showcase_is_pre_staged(): void
    {
        // The open receivable (kanji client name)…
        $mr = $this->query->fetchOne(
            "SELECT total_cents, due_at FROM manual_receivables WHERE organization_id = ? AND client_name = '株式会社山田工務店' AND status = 'open'",
            [self::ORG],
        );
        self::assertIsArray($mr);
        self::assertSame(42350000, (int) $mr['total_cents']);
        self::assertSame('2026-07-20', (string) $mr['due_at']); // T+10, due soon

        // …and its exact-amount unmatched deposit under the katakana transfer name.
        $tx = $this->query->fetchOne(
            "SELECT amount_cents FROM bank_transactions WHERE organization_id = ? AND counterparty_text = 'ヤマダコウムテン（カ' AND status = 'unmatched'",
            [self::ORG],
        );
        self::assertIsArray($tx);
        self::assertSame(42350000, (int) $tx['amount_cents']);
    }

    public function test_dunning_history_aligns_with_the_live_upstream_fixture(): void
    {
        // INV-2026-057 was noticed 2 days ago — the min-interval throttle showcase.
        $row = $this->query->fetchOne(
            "SELECT sent_at FROM dunning_notices WHERE organization_id = ? AND invoice_number = 'INV-2026-057'",
            [self::ORG],
        );
        self::assertIsArray($row);
        self::assertStringStartsWith('2026-07-08', (string) $row['sent_at']);
    }

    public function test_seed_is_deterministic_for_the_same_org_and_day(): void
    {
        $before = $this->countRows('SELECT COUNT(*) FROM bank_transactions WHERE organization_id = ?');

        // A different org id on a second database yields the same structure.
        $dbPath = sys_get_temp_dir() . '/' . uniqid('clear-demo-seed2-', true) . '.sqlite';
        $query = DatabaseTestKit::sqlite($dbPath)->queryExecutor;
        SchemaFixture::createOrganizations($query);
        SchemaFixture::createUsers($query);
        SchemaFixture::createBankAccounts($query);
        SchemaFixture::createBankImportBatches($query);
        SchemaFixture::createBankTransactions($query);
        SchemaFixture::createPaymentReconciliations($query);
        SchemaFixture::createReconciliationAllocations($query);
        SchemaFixture::createClientCredits($query);
        SchemaFixture::createClearSettings($query);
        SchemaFixture::createDunningNotices($query);
        SchemaFixture::createDunningPauses($query);
        SchemaFixture::createManualReceivables($query);
        SchemaFixture::createAuditEvents($query);
        $query->execute("INSERT INTO organizations (id, slug, name, created_at, is_deleted) VALUES (42, 'demo-x', 'Demo', '2026-07-10', 0)");
        $query->execute("INSERT INTO users (organization_id, email, role, status, password_hash, is_deleted) VALUES (42, 'a@demo-x.example', 'admin', 'active', 'x', 0)");
        (new DemoDataSeeder($query, new FixedClock('2026-07-10T09:00:00+00:00')))->seed(42, DemoTemplate::Standard);

        $row = $query->fetchOne('SELECT COUNT(*) AS n FROM bank_transactions WHERE organization_id = 42');
        self::assertSame($before, is_array($row) ? (int) $row['n'] : -1);
        @unlink($dbPath);
    }
}
