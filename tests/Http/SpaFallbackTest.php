<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use NeneClear\Http\SpaFallback;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class SpaFallbackTest extends TestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/' . uniqid('clear-spa-', true);
        mkdir($this->publicDir . '/assets', recursive: true);
        file_put_contents($this->publicDir . '/assets/index.html', '<html>shell</html>');
    }

    protected function tearDown(): void
    {
        @unlink($this->publicDir . '/assets/index.html');
        @rmdir($this->publicDir . '/assets');
        @rmdir($this->publicDir);
    }

    private function request(string $method, string $path, string $accept): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest($method, $path)->withHeader('Accept', $accept);
    }

    public function test_browser_navigation_gets_the_shell(): void
    {
        $shell = SpaFallback::shellPath($this->request('GET', '/receivables', 'text/html,application/xhtml+xml'), $this->publicDir);

        self::assertSame($this->publicDir . '/assets/index.html', $shell);
    }

    public function test_api_clients_never_get_the_shell(): void
    {
        self::assertNull(SpaFallback::shellPath($this->request('GET', '/receivables', 'application/json'), $this->publicDir));
        self::assertNull(SpaFallback::shellPath($this->request('GET', '/receivables', '*/*'), $this->publicDir));
        // Explicit JSON wins even when text/html is also present.
        self::assertNull(SpaFallback::shellPath($this->request('GET', '/receivables', 'text/html,application/json'), $this->publicDir));
    }

    public function test_non_get_and_reserved_prefixes_are_never_intercepted(): void
    {
        $accept = 'text/html';
        self::assertNull(SpaFallback::shellPath($this->request('POST', '/receivables', $accept), $this->publicDir));
        self::assertNull(SpaFallback::shellPath($this->request('GET', '/health', $accept), $this->publicDir));
        self::assertNull(SpaFallback::shellPath($this->request('GET', '/machine/health', $accept), $this->publicDir));
        self::assertNull(SpaFallback::shellPath($this->request('GET', '/assets/app.js', $accept), $this->publicDir));
        // The demo start route serves its own seat page (#275).
        self::assertNull(SpaFallback::shellPath($this->request('GET', '/demo/standard', $accept), $this->publicDir));
    }

    public function test_missing_build_falls_through_to_the_application(): void
    {
        unlink($this->publicDir . '/assets/index.html');

        self::assertNull(SpaFallback::shellPath($this->request('GET', '/receivables', 'text/html'), $this->publicDir));
    }
}
