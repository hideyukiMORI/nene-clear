<?php

declare(strict_types=1);

namespace NeneClear\Tests\Demo;

use Nene2\Demo\DemoCapacityExceededException;
use Nene2\Demo\DemoCapacityGuardInterface;
use Nene2\Demo\DemoConfig;
use Nene2\Demo\DemoDataSeederInterface;
use Nene2\Demo\DemoSessionSeaterInterface;
use Nene2\Demo\DemoTemplateKeyInterface;
use Nene2\Demo\DemoThrottledException;
use Nene2\Demo\DisposableOrgProvisionerInterface;
use Nene2\Demo\ProvisionedDemoOrg;
use Nene2\Demo\StartDisposableDemoHandler;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Routing\Router;
use NeneClear\Demo\DemoBrowserErrorPage;
use NeneClear\Demo\DemoTemplate;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The public demo link (`clear.ayane.co.jp/demo/standard`) is opened by
 * non-technical visitors in a browser, so its errors must render as a branded
 * explanation page with its own CSP — not raw Problem Details JSON — while
 * API-shaped clients and the success seat page stay byte-identical (#296,
 * fleet parity with invoice/deal/vault).
 *
 * The negotiation lives in the framework's {@see StartDisposableDemoHandler}
 * (NENE2 v1.10.0, ADR 0018); this test locks the outward behavior at both
 * levels: the renderer's page itself and the negotiated route surface with
 * the renderer wired in.
 */
final class DemoBrowserErrorPageTest extends TestCase
{
    private Psr17Factory $psr17;
    private DemoBrowserErrorPage $page;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->page = new DemoBrowserErrorPage($this->psr17, throttleLimit: 30, throttleWindowSeconds: 3600);
    }

    public function test_429_page_renders_limit_countdown_and_disabled_retry(): void
    {
        $result = $this->page->render(429, 3519);

        self::assertSame(429, $result->getStatusCode());
        self::assertStringStartsWith('text/html', $result->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $result->getHeaderLine('Cache-Control'));

        $body = (string) $result->getBody();
        self::assertStringContainsString('1時間あたり30回まで', $body);
        self::assertStringContainsString('id="clock">58:39<', $body, 'initial clock is server-rendered (works without JS)');
        self::assertStringContainsString('var remain = 3519;', $body, 'countdown script seeds from Retry-After');
        self::assertStringContainsString('id="retry" type="button" disabled', $body, 'retry stays disabled until the window resets');
        self::assertStringContainsString('429 · DEMO_RATE_LIMIT', $body);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $body);
        self::assertStringContainsString('NeNe Clear', $body);
    }

    public function test_html_page_carries_its_own_csp_allowing_inline_assets(): void
    {
        $csp = $this->page->render(429, 60)->getHeaderLine('Content-Security-Policy');

        self::assertStringContainsString("style-src 'unsafe-inline'", $csp);
        self::assertStringContainsString("script-src 'unsafe-inline'", $csp, 'framework default CSP has no script-src — the countdown needs it');
        self::assertStringContainsString("default-src 'none'", $csp, 'app-wide default-src self would block the inline design');
    }

    public function test_503_page_renders_capacity_copy_with_enabled_retry_and_no_countdown(): void
    {
        $result = $this->page->render(503, null);

        $body = (string) $result->getBody();
        self::assertSame(503, $result->getStatusCode());
        self::assertStringContainsString('満席', $body);
        self::assertStringContainsString('id="retry" type="button">', $body, 'capacity clears on its own — retry starts enabled');
        self::assertStringNotContainsString('id="clock"', $body);
        self::assertStringContainsString('503 · DEMO_CAPACITY', $body);
    }

    public function test_404_page_renders_unavailable_copy_without_retry(): void
    {
        $result = $this->page->render(404, null);

        $body = (string) $result->getBody();
        self::assertSame(404, $result->getStatusCode());
        self::assertStringContainsString('ご利用いただけません', $body);
        self::assertStringNotContainsString('id="retry"', $body, 'reloading a dead link would just 404 again');
        self::assertStringContainsString('404 · DEMO_NOT_FOUND', $body);
    }

    public function test_browser_429_on_the_route_gets_the_page_with_transport_invariants(): void
    {
        $handler = $this->handler(throttledForSeconds: 3519);
        $result = $handler->handle($this->request('text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'));

        self::assertSame(429, $result->getStatusCode());
        self::assertStringStartsWith('text/html', $result->getHeaderLine('Content-Type'));
        self::assertSame('3519', $result->getHeaderLine('Retry-After'), 'Retry-After must survive negotiation');
        self::assertSame('noindex', $result->getHeaderLine('X-Robots-Tag'));

        $body = (string) $result->getBody();
        self::assertStringContainsString('デモのご利用が集中しています', $body);
        self::assertStringContainsString('var remain = 3519;', $body, 'countdown seeds from the 429 Retry-After');
        self::assertStringNotContainsString('standard', $body, 'fixed copy only — never echo the template segment');
    }

    public function test_browser_503_on_the_route_gets_the_capacity_page(): void
    {
        $handler = $this->handler(capacityExceeded: true);
        $result = $handler->handle($this->request('text/html'));

        self::assertSame(503, $result->getStatusCode());
        self::assertStringStartsWith('text/html', $result->getHeaderLine('Content-Type'));
        self::assertStringContainsString('満席', (string) $result->getBody());
    }

    public function test_browser_404_on_the_route_gets_the_unavailable_page(): void
    {
        $handler = $this->handler(throttledForSeconds: 3519);
        $result = $handler->handle($this->request('text/html', template: 'no-such-template'));

        self::assertSame(404, $result->getStatusCode());
        self::assertStringStartsWith('text/html', $result->getHeaderLine('Content-Type'));
        self::assertStringContainsString('ご利用いただけません', (string) $result->getBody());
        self::assertStringNotContainsString('no-such-template', (string) $result->getBody(), 'never echo the template segment');
    }

    public function test_api_clients_keep_problem_details_untouched(): void
    {
        $baseline = $this->handler(throttledForSeconds: 60)->handle($this->request(''));

        foreach (['application/json', '*/*', ''] as $accept) {
            $result = $this->handler(throttledForSeconds: 60)->handle($this->request($accept));

            self::assertSame(429, $result->getStatusCode());
            self::assertStringStartsWith('application/problem+json', $result->getHeaderLine('Content-Type'));
            self::assertSame('60', $result->getHeaderLine('Retry-After'));
            self::assertSame((string) $baseline->getBody(), (string) $result->getBody(), 'API error body must stay byte-identical');
            self::assertSame($baseline->getHeaders(), $result->getHeaders(), 'API error headers must stay identical');
        }
    }

    public function test_success_seat_page_is_untouched_even_for_browsers(): void
    {
        $seatPage = $this->psr17->createResponse(200)->withHeader('Content-Type', 'text/html; charset=utf-8');
        $result = $this->handler(seatResponse: $seatPage)->handle($this->request('text/html'));

        self::assertSame($seatPage, $result, 'the seater response must pass through negotiation unchanged');
    }

    private function request(string $accept, string $template = 'standard'): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest('GET', '/demo/' . $template)
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, [StartDisposableDemoHandler::TEMPLATE_PARAMETER => $template]);

        return $accept === '' ? $request : $request->withHeader('Accept', $accept);
    }

    /**
     * The real framework handler with the Clear renderer wired in — guard
     * outcomes are stubbed so each error path can be forced deterministically.
     */
    private function handler(
        int $throttledForSeconds = 0,
        bool $capacityExceeded = false,
        ?ResponseInterface $seatResponse = null,
    ): StartDisposableDemoHandler {
        $psr17 = $this->psr17;

        $guard = new class ($throttledForSeconds, $capacityExceeded) implements DemoCapacityGuardInterface {
            public function __construct(
                private readonly int $throttledForSeconds,
                private readonly bool $capacityExceeded,
            ) {
            }

            public function assertHasCapacity(): void
            {
                if ($this->capacityExceeded) {
                    throw new DemoCapacityExceededException('The demo capacity is exhausted.');
                }
            }

            public function assertNotThrottled(ServerRequestInterface $request): void
            {
                if ($this->throttledForSeconds > 0) {
                    throw new DemoThrottledException('Too many demo starts.', $this->throttledForSeconds);
                }
            }
        };

        $provisioner = new class () implements DisposableOrgProvisionerInterface {
            public function provision(string $slug, string $template): ProvisionedDemoOrg
            {
                return new ProvisionedDemoOrg(1, $slug, 1);
            }
        };

        $seeder = new class () implements DemoDataSeederInterface {
            public function seed(int $orgId, DemoTemplateKeyInterface $template): void
            {
            }
        };

        $seater = new class ($seatResponse ?? $psr17->createResponse(200)->withHeader('Content-Type', 'text/html; charset=utf-8')) implements DemoSessionSeaterInterface {
            public function __construct(private readonly ResponseInterface $seatResponse)
            {
            }

            public function seatAndRedirect(ServerRequestInterface $request, ProvisionedDemoOrg $org): ResponseInterface
            {
                return $this->seatResponse;
            }
        };

        return new StartDisposableDemoHandler(
            new DemoConfig(demoMode: true),
            $guard,
            $provisioner,
            $seeder,
            $seater,
            new ProblemDetailsResponseFactory($psr17, $psr17),
            DemoTemplate::class,
            $this->page,
        );
    }
}
