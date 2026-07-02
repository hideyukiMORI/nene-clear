<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use NeneClear\Http\ApplicationFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

/**
 * Machine surface wiring (issue #182): the auth-gated GET /machine/health
 * reports this application's own release version so deployment tooling can
 * track the installed version. The public GET /health must not expose it.
 */
final class MachineHealthTest extends TestCase
{
    private const MACHINE_KEY = 'test-machine-key';
    private const APP_VERSION = '0.1.0-test';

    public function test_machine_health_reports_app_version_with_valid_key(): void
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', '/machine/health')
            ->withHeader('X-NENE2-API-Key', self::MACHINE_KEY);

        $response = $this->app()->handle($request);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('ok', $data['status'] ?? null);
        self::assertSame(self::APP_VERSION, $data['version'] ?? null);
        self::assertArrayHasKey('framework_version', $data);
    }

    public function test_machine_health_rejects_a_missing_key(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', '/machine/health');

        $response = $this->app()->handle($request);

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_public_health_does_not_expose_the_app_version(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', '/health');

        $response = $this->app()->handle($request);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayNotHasKey('version', $data);
    }

    public function test_machine_health_omits_version_when_not_injected(): void
    {
        $app = ApplicationFactory::create(
            debug: false,
            allowedOrigins: [],
            machineApiKey: self::MACHINE_KEY,
        );
        $request = (new Psr17Factory())
            ->createServerRequest('GET', '/machine/health')
            ->withHeader('X-NENE2-API-Key', self::MACHINE_KEY);

        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayNotHasKey('version', $data);
    }

    private function app(): \Psr\Http\Server\RequestHandlerInterface
    {
        // Health-only surface (no DB / JWT) — the machine surface must work even
        // before the database is configured, mirroring public_html/index.php.
        return ApplicationFactory::create(
            debug: false,
            allowedOrigins: [],
            machineApiKey: self::MACHINE_KEY,
            appVersion: self::APP_VERSION,
        );
    }
}
