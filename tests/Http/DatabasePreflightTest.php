<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\Preflight\CandidateProfile;
use Nene2\Database\Preflight\DefaultDatabaseCandidateInspector;
use NeneClear\Database\ApplicationDatabaseIdentity;
use NeneClear\Database\MigrationVersions;
use NeneClear\Http\ApplicationFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Candidate-database preflight adoption (issue #183): the opt-in
 * `POST /machine/database/preflight` inspects an env-declared candidate
 * read-only and returns a structured verdict.
 */
final class DatabasePreflightTest extends TestCase
{
    private const MACHINE_KEY = 'test-machine-key';

    private string $candidatePath;

    protected function setUp(): void
    {
        $this->candidatePath = tempnam(sys_get_temp_dir(), 'clear-candidate-') . '.sqlite3';
    }

    protected function tearDown(): void
    {
        @unlink($this->candidatePath);
    }

    public function test_preflights_a_fresh_sqlite_candidate(): void
    {
        $response = $this->post(['candidate' => 'fresh']);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertTrue($data['reachable'] ?? null);
        self::assertContains(
            $data['recommendation'] ?? null,
            ['safe', 'needs_migration', 'needs_review', 'refuse'],
        );
        self::assertSame('fresh', $data['migration_state'] ?? null);
    }

    public function test_recognizes_a_migrated_clear_database_as_its_own(): void
    {
        // Simulate a fully-migrated Clear database: the phinxlog ledger holds
        // every known migration version and the identity marker is stamped.
        $pdo = new \PDO('sqlite:' . $this->candidatePath);
        $pdo->exec('CREATE TABLE phinxlog (version BIGINT NOT NULL, migration_name TEXT, breakpoint INTEGER)');
        $insert = $pdo->prepare('INSERT INTO phinxlog (version) VALUES (:version)');
        foreach (MigrationVersions::fromDirectory(dirname(__DIR__, 2) . '/database/migrations') as $version) {
            $insert->execute([':version' => $version]);
        }
        $pdo->exec('CREATE TABLE nene2_app_identity (application_id TEXT NOT NULL, tenant_id TEXT NULL)');
        $pdo->exec("INSERT INTO nene2_app_identity (application_id, tenant_id) VALUES ('nene-clear', NULL)");
        unset($pdo);

        $response = $this->post(['candidate' => 'fresh']);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertTrue($data['reachable'] ?? null);
        self::assertTrue($data['schema_recognized'] ?? null);
        self::assertSame('compatible', $data['migration_state'] ?? null);
        self::assertSame('match', $data['app_identity'] ?? null);
    }

    public function test_rejects_an_unknown_candidate_id(): void
    {
        $response = $this->post(['candidate' => 'nope']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function test_requires_the_machine_api_key(): void
    {
        $request = (new Psr17Factory())
            ->createServerRequest('POST', '/machine/database/preflight')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string) json_encode(['candidate' => 'fresh']));

        $response = $this->app()->handle($request);

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_endpoint_is_absent_without_the_inspector(): void
    {
        $app = ApplicationFactory::create(
            debug: false,
            allowedOrigins: [],
            machineApiKey: self::MACHINE_KEY,
        );
        $request = (new Psr17Factory())
            ->createServerRequest('POST', '/machine/database/preflight')
            ->withHeader('X-NENE2-API-Key', self::MACHINE_KEY)
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string) json_encode(['candidate' => 'fresh']));

        $response = $app->handle($request);

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @param array<string, string> $body
     */
    private function post(array $body): ResponseInterface
    {
        $request = (new Psr17Factory())
            ->createServerRequest('POST', '/machine/database/preflight')
            ->withHeader('X-NENE2-API-Key', self::MACHINE_KEY)
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string) json_encode($body));

        return $this->app()->handle($request);
    }

    private function app(): RequestHandlerInterface
    {
        // Mirrors public_html/index.php: inspector = app migration versions +
        // identity; candidates resolved only from the application's own config.
        return ApplicationFactory::create(
            debug: false,
            allowedOrigins: [],
            machineApiKey: self::MACHINE_KEY,
            databaseCandidateInspector: new DefaultDatabaseCandidateInspector(
                applicationMigrationVersions: MigrationVersions::fromDirectory(
                    dirname(__DIR__, 2) . '/database/migrations',
                ),
                ledgerTable: 'phinxlog',
                applicationIdentity: ApplicationDatabaseIdentity::identity(),
            ),
            databaseCandidateProfiles: [
                'fresh' => new CandidateProfile(
                    id: 'fresh',
                    connectionFactory: new PdoConnectionFactory(
                        DatabaseConfig::sqlite($this->candidatePath, 'candidate'),
                    ),
                ),
            ],
        );
    }
}
