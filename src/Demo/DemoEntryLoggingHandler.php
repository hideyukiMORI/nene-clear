<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use Nene2\Demo\StartDisposableDemoHandler;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Records the acquisition context of a demo entry (`GET /demo/{template}`)
 * before delegating to the wrapped framework handler — a `Nene2\Demo`
 * decorator ({@see \Nene2\Demo\DemoRouteRegistrar} accepts any PSR-15 handler,
 * ADR 0018).
 *
 * Why server-side: the seat-page landing replaces the URL as it drops the
 * visitor into the SPA, so `utm_*` query parameters never survive to the
 * cookieless client beacon (which only sees the post-landing回遊). Attribution
 * must therefore be captured here, at entry. Only the `Referer` and the three
 * standard UTM parameters are logged — no cookie, no user identity, no request
 * body; nothing that identifies a person.
 */
final readonly class DemoEntryLoggingHandler implements RequestHandlerInterface
{
    /** Cap logged free-text so a crafted Referer/UTM cannot bloat a log line. */
    private const int MAX_VALUE_LENGTH = 512;

    public function __construct(
        private RequestHandlerInterface $inner,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();

        $this->logger->info('Demo entry recorded.', [
            'template' => Router::param($request, StartDisposableDemoHandler::TEMPLATE_PARAMETER),
            'referer' => $this->clean($request->getHeaderLine('Referer')),
            'utm_source' => $this->cleanParam($query['utm_source'] ?? null),
            'utm_medium' => $this->cleanParam($query['utm_medium'] ?? null),
            'utm_campaign' => $this->cleanParam($query['utm_campaign'] ?? null),
        ]);

        return $this->inner->handle($request);
    }

    private function clean(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, self::MAX_VALUE_LENGTH);
    }

    private function cleanParam(mixed $value): ?string
    {
        return is_string($value) ? $this->clean($value) : null;
    }
}
