<?php

declare(strict_types=1);

namespace NeneClear\Tests\Demo;

use Nene2\Demo\StartDisposableDemoHandler;
use Nene2\Routing\Router;
use NeneClear\Demo\DemoEntryLoggingHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use Stringable;

final class DemoEntryLoggingHandlerTest extends TestCase
{
    public function test_records_referer_and_utm_before_delegating(): void
    {
        $logger = $this->recordingLogger();
        $inner = $this->spyHandler();

        $request = $this->demoRequest('standard', ['utm_source' => 'facebook', 'utm_medium' => 'social', 'utm_campaign' => 'launch'])
            ->withHeader('Referer', 'https://www.facebook.com/');

        $response = (new DemoEntryLoggingHandler($inner, $logger))->handle($request);

        self::assertTrue($inner->called, 'inner handler must run');
        self::assertSame(299, $response->getStatusCode());

        self::assertCount(1, $logger->records);
        [$level, $message, $context] = $logger->records[0];
        self::assertSame('info', $level);
        self::assertSame('Demo entry recorded.', $message);
        self::assertSame([
            'template' => 'standard',
            'referer' => 'https://www.facebook.com/',
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => 'launch',
        ], $context);
    }

    public function test_absent_referer_and_utm_are_recorded_as_null(): void
    {
        $logger = $this->recordingLogger();

        (new DemoEntryLoggingHandler($this->spyHandler(), $logger))->handle($this->demoRequest('standard', []));

        self::assertSame([
            'template' => 'standard',
            'referer' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
        ], $logger->records[0][2]);
    }

    public function test_non_string_utm_array_is_dropped(): void
    {
        $logger = $this->recordingLogger();

        // ?utm_source[]=a — PSR-7 parses this into an array; it must not be logged.
        (new DemoEntryLoggingHandler($this->spyHandler(), $logger))->handle($this->demoRequest('standard', ['utm_source' => ['a', 'b']]));

        self::assertNull($logger->records[0][2]['utm_source']);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function demoRequest(string $template, array $query): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/demo/' . $template)
            ->withQueryParams($query)
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, [StartDisposableDemoHandler::TEMPLATE_PARAMETER => $template]);
    }

    /**
     * @return RequestHandlerInterface&object{called: bool}
     */
    private function spyHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public bool $called = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;

                return (new Psr17Factory())->createResponse(299);
            }
        };
    }

    /**
     * @return AbstractLogger&object{records: list<array{string, string, array<string, mixed>}>}
     */
    private function recordingLogger(): AbstractLogger
    {
        return new class () extends AbstractLogger {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $records = [];

            /**
             * @param array<string, mixed> $context
             */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
    }
}
