<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use NeneClear\Http\DemoAnalyticsInjection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DemoAnalyticsInjectionTest extends TestCase
{
    private const string SHELL = "<!doctype html>\n<html><head>\n    <title>NeNe Clear</title>\n  </head>\n<body></body></html>";

    public function test_disabled_when_env_is_unset_or_empty(): void
    {
        self::assertFalse(DemoAnalyticsInjection::fromEnv([])->isEnabled());
        self::assertFalse(DemoAnalyticsInjection::fromEnv(['DEMO_ANALYTICS_ENDPOINT' => ''])->isEnabled());
        self::assertFalse(DemoAnalyticsInjection::fromEnv(['DEMO_ANALYTICS_ENDPOINT' => '   '])->isEnabled());
    }

    public function test_disabled_leaves_shell_byte_identical_and_emits_no_csp(): void
    {
        $analytics = DemoAnalyticsInjection::fromEnv([]);

        self::assertSame(self::SHELL, $analytics->injectHead(self::SHELL));
        self::assertNull($analytics->contentSecurityPolicy());
        self::assertSame('', $analytics->beaconTag());
    }

    public function test_enabled_injects_the_beacon_before_head_close(): void
    {
        $analytics = DemoAnalyticsInjection::fromEnv(['DEMO_ANALYTICS_ENDPOINT' => 'https://stats.ayane.co.jp']);

        self::assertTrue($analytics->isEnabled());

        $out = $analytics->injectHead(self::SHELL);
        self::assertStringContainsString(
            '<script data-goatcounter="https://stats.ayane.co.jp/count" async src="https://stats.ayane.co.jp/count.js"></script>',
            $out,
        );
        // Injected inside the head, before the closing tag.
        self::assertStringContainsString('data-goatcounter', $out);
        self::assertLessThan(strpos($out, '</head>'), strpos($out, 'data-goatcounter'));
        // Body is untouched.
        self::assertStringContainsString('<body></body>', $out);
    }

    public function test_enabled_csp_allows_only_self_and_the_endpoint_origin(): void
    {
        $csp = DemoAnalyticsInjection::fromEnv(['DEMO_ANALYTICS_ENDPOINT' => 'https://stats.ayane.co.jp'])
            ->contentSecurityPolicy();

        self::assertNotNull($csp);
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("script-src 'self' https://stats.ayane.co.jp", $csp);
        self::assertStringContainsString("connect-src 'self' https://stats.ayane.co.jp", $csp);
        self::assertStringContainsString("img-src 'self' data: https://stats.ayane.co.jp", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedEndpoints(): iterable
    {
        yield 'http (not https)' => ['http://stats.ayane.co.jp'];
        yield 'with path' => ['https://stats.ayane.co.jp/count'];
        yield 'with trailing slash path' => ['https://stats.ayane.co.jp/'];
        yield 'with query' => ['https://stats.ayane.co.jp?a=1'];
        yield 'with fragment' => ['https://stats.ayane.co.jp#x'];
        yield 'with credentials' => ['https://user:pass@stats.ayane.co.jp'];
        yield 'no host' => ['https://'];
        yield 'quote injection' => ['https://x"/onerror'];
        yield 'space injection' => ['https://x y'];
        yield 'header injection' => ['https://x;script-src'];
    }

    #[DataProvider('rejectedEndpoints')]
    public function test_malformed_endpoints_disable_injection(string $endpoint): void
    {
        $analytics = DemoAnalyticsInjection::fromEnv(['DEMO_ANALYTICS_ENDPOINT' => $endpoint]);

        self::assertFalse($analytics->isEnabled(), $endpoint);
        self::assertSame(self::SHELL, $analytics->injectHead(self::SHELL));
        self::assertNull($analytics->contentSecurityPolicy());
    }

    public function test_endpoint_with_explicit_port_is_accepted(): void
    {
        $analytics = DemoAnalyticsInjection::fromEnv(['DEMO_ANALYTICS_ENDPOINT' => 'https://stats.ayane.co.jp:8443']);

        self::assertTrue($analytics->isEnabled());
        self::assertStringContainsString('https://stats.ayane.co.jp:8443/count.js', $analytics->beaconTag());
    }

    public function test_shell_without_head_marker_is_returned_unchanged(): void
    {
        $analytics = DemoAnalyticsInjection::fromEnv(['DEMO_ANALYTICS_ENDPOINT' => 'https://stats.ayane.co.jp']);
        $noHead = '<html><body>no head</body></html>';

        self::assertSame($noHead, $analytics->injectHead($noHead));
    }
}
