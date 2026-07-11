<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use NeneClear\Http\RuntimeBootstrap;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Pins the composition-root behaviour (#294): ConfigLoader/AppConfig-driven
 * wiring keeps the resilience contract — health always answers, the admin
 * surface mounts only when a JWT secret resolves (fail-close, #285), and the
 * transitional legacy env name still works for one release.
 */
final class RuntimeBootstrapTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @param array<string, string> $overrides
     */
    private function handle(array $overrides, string $method = 'GET', string $path = '/health'): ResponseInterface
    {
        $dbPath = sys_get_temp_dir() . '/' . uniqid('clear-bootstrap-', true) . '.sqlite';
        $this->tempFiles[] = $dbPath;

        $application = RuntimeBootstrap::application(dirname(__DIR__, 2), array_merge([
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'false',
            'DB_ADAPTER' => 'sqlite',
            'DB_NAME' => $dbPath,
            'NENE2_LOCAL_JWT_SECRET' => '',
            'NENE_CLEAR_JWT_SECRET' => '',
            'NENE2_ALLOW_DEV_SECRET' => '',
            'DEMO_MODE' => '',
        ], $overrides));

        $request = (new Psr17Factory())->createServerRequest($method, $path)
            ->withHeader('Accept', 'application/json');

        return $application->handle($request);
    }

    public function test_health_answers_even_without_a_jwt_secret(): void
    {
        self::assertSame(200, $this->handle([])->getStatusCode());
    }

    public function test_admin_surface_is_unmounted_without_a_jwt_secret(): void
    {
        self::assertSame(404, $this->handle([], 'GET', '/admin/receivables')->getStatusCode());
    }

    public function test_admin_surface_mounts_with_the_canonical_env(): void
    {
        $response = $this->handle(
            ['NENE2_LOCAL_JWT_SECRET' => str_repeat('a', 64)],
            'GET',
            '/admin/receivables',
        );

        // 401 (bearer required) — the route exists, unlike the 404 above.
        self::assertSame(401, $response->getStatusCode());
    }

    public function test_legacy_env_name_still_mounts_the_admin_surface(): void
    {
        $response = $this->handle(
            ['NENE_CLEAR_JWT_SECRET' => str_repeat('b', 64)],
            'GET',
            '/admin/receivables',
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_dev_secret_opt_in_mounts_the_admin_surface_in_local(): void
    {
        $response = $this->handle(
            ['NENE2_ALLOW_DEV_SECRET' => '1'],
            'GET',
            '/admin/receivables',
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_malformed_configuration_degrades_to_the_health_surface(): void
    {
        // A garbage DB_PORT used to be caught by the guarded DB construction;
        // with ConfigLoader it throws at load() time — the bootstrap must
        // degrade to the public/health surface instead of crashing (#294).
        $overrides = ['DB_PORT' => 'not-a-port', 'NENE2_LOCAL_JWT_SECRET' => str_repeat('c', 64)];

        self::assertSame(200, $this->handle($overrides)->getStatusCode());
        self::assertSame(404, $this->handle($overrides, 'GET', '/admin/receivables')->getStatusCode());
    }

    public function test_production_ignores_the_dev_secret_opt_in(): void
    {
        $response = $this->handle(
            ['APP_ENV' => 'production', 'NENE2_ALLOW_DEV_SECRET' => '1'],
            'GET',
            '/admin/receivables',
        );

        self::assertSame(404, $response->getStatusCode());
    }
}
