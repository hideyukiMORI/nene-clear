<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use NeneClear\Http\AuthorizationHeaderFallback;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class AuthorizationHeaderFallbackTest extends TestCase
{
    private function request(): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/admin/auth/me');
    }

    public function test_standard_header_wins_over_the_mirror(): void
    {
        $request = $this->request()
            ->withHeader('Authorization', 'Bearer standard')
            ->withHeader('X-Authorization', 'Bearer mirror');

        $result = AuthorizationHeaderFallback::apply($request);

        self::assertSame('Bearer standard', $result->getHeaderLine('Authorization'));
    }

    public function test_mirror_is_adopted_when_standard_header_is_absent(): void
    {
        $request = $this->request()->withHeader('X-Authorization', 'Bearer mirror');

        $result = AuthorizationHeaderFallback::apply($request);

        self::assertSame('Bearer mirror', $result->getHeaderLine('Authorization'));
    }

    public function test_empty_standard_header_counts_as_absent(): void
    {
        $request = $this->request()
            ->withHeader('Authorization', '')
            ->withHeader('X-Authorization', 'Bearer mirror');

        $result = AuthorizationHeaderFallback::apply($request);

        self::assertSame('Bearer mirror', $result->getHeaderLine('Authorization'));
    }

    public function test_no_headers_leaves_the_request_unchanged(): void
    {
        $result = AuthorizationHeaderFallback::apply($this->request());

        self::assertSame('', $result->getHeaderLine('Authorization'));
        self::assertFalse($result->hasHeader('Authorization'));
    }
}
