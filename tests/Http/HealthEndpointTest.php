<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use NeneClear\Http\ApplicationFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class HealthEndpointTest extends TestCase
{
    public function test_health_returns_ok_status(): void
    {
        $app = ApplicationFactory::create(debug: false, allowedOrigins: []);
        $request = (new Psr17Factory())->createServerRequest('GET', '/health');

        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('ok', $data['status'] ?? null);
    }
}
