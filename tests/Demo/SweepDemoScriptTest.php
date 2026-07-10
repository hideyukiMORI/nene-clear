<?php

declare(strict_types=1);

namespace NeneClear\Tests\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real `tools/sweep-demo.php` as a subprocess with the host timezone
 * pinned to Asia/Tokyo — the production (HETEML) configuration where #280 was
 * caught live. `created_at` is written in UTC, so a bare `DateTimeImmutable`
 * parse reads every org as 9 hours older than it is and the hourly cron reaps
 * freshly provisioned demo orgs on the spot. The script must parse UTC
 * explicitly: fresh orgs survive, genuinely expired ones are still reaped.
 */
final class SweepDemoScriptTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-sweep-script-', true) . '.sqlite';
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

    public function testFreshOrgSurvivesAndExpiredOrgIsReapedOnAJstHost(): void
    {
        $nowUtc = time();
        $this->insertOrg(1, 'demo-fresh', gmdate('Y-m-d H:i:s', $nowUtc - 60));            // 1 minute old
        $this->insertOrg(2, 'demo-expired', gmdate('Y-m-d H:i:s', $nowUtc - 4 * 3600));    // past the 3h TTL
        $this->insertOrg(3, 'demo', gmdate('Y-m-d H:i:s', $nowUtc - 30 * 24 * 3600));      // fixed showcase org (no hyphen)

        $output = $this->runSweep('Asia/Tokyo');

        self::assertStringContainsString('2 org(s) total, 1 expired, 0 overflow, 1 reaped', $output);
        self::assertSame(['demo', 'demo-fresh'], $this->remainingSlugs());
    }

    public function testBehavesIdenticallyOnAUtcHost(): void
    {
        $nowUtc = time();
        $this->insertOrg(1, 'demo-fresh', gmdate('Y-m-d H:i:s', $nowUtc - 60));
        $this->insertOrg(2, 'demo-expired', gmdate('Y-m-d H:i:s', $nowUtc - 4 * 3600));

        $output = $this->runSweep('UTC');

        self::assertStringContainsString('2 org(s) total, 1 expired, 0 overflow, 1 reaped', $output);
        self::assertSame(['demo-fresh'], $this->remainingSlugs());
    }

    private function insertOrg(int $id, string $slug, string $createdAtUtc): void
    {
        $this->query->execute(
            "INSERT INTO organizations (id, slug, name, created_at, is_deleted) VALUES (?, ?, 'Demo', ?, 0)",
            [$id, $slug, $createdAtUtc],
        );
    }

    /** @return list<string> remaining org slugs, sorted */
    private function remainingSlugs(): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['slug'],
            $this->query->fetchAll('SELECT slug FROM organizations ORDER BY slug', []),
        );
    }

    private function runSweep(string $timezone): string
    {
        $root = dirname(__DIR__, 2);
        $command = [
            PHP_BINARY,
            '-d', 'date.timezone=' . $timezone,
            $root . '/tools/sweep-demo.php',
        ];

        // Explicit env wins over the repo's .env: Dotenv::safeLoad() is immutable.
        $env = [
            'DB_ADAPTER' => 'sqlite',
            'DB_NAME' => $this->dbPath,
            'DEMO_TTL_HOURS' => '3',
            'DEMO_MAX_ORGS' => '200',
            'PATH' => (string) getenv('PATH'),
        ];

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, 'sweep-demo.php failed: ' . $stderr);

        return $stdout;
    }
}
