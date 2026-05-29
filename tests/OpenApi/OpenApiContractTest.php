<?php

declare(strict_types=1);

namespace NeneClear\Tests\OpenApi;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards that the OpenAPI contract stays parseable and documents the shipped
 * runtime behavior. Expands per endpoint as Phase 1 routes land.
 */
final class OpenApiContractTest extends TestCase
{
    public function test_openapi_document_parses_and_documents_get_health(): void
    {
        $path = dirname(__DIR__, 2) . '/docs/openapi/openapi.yaml';
        self::assertFileExists($path);

        $doc = Yaml::parseFile($path);
        self::assertIsArray($doc);
        self::assertSame('3.1.0', $doc['openapi'] ?? null);

        $paths = $doc['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertArrayHasKey('/health', $paths);

        $health = $paths['/health'];
        self::assertIsArray($health);
        self::assertArrayHasKey('get', $health);

        $get = $health['get'];
        self::assertIsArray($get);
        self::assertSame('getHealth', $get['operationId'] ?? null);

        $responses = $get['responses'] ?? null;
        self::assertIsArray($responses);
        self::assertArrayHasKey('200', $responses);
    }
}
