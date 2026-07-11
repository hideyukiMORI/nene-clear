<?php

declare(strict_types=1);

namespace NeneClear\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Decides whether a request is a browser navigation that should receive the
 * built SPA shell (`public_html/assets/index.html`) instead of hitting the
 * API router.
 *
 * Browsers send `Accept: text/html,...` — API clients send
 * `Accept: application/json` or a bare wildcard only, so the shell is served
 * only when `text/html` is explicit (and `application/json` is not). API
 * surfaces (`/health`, `/machine`), static assets, and the demo start route
 * (which serves its own seat page, #275) are never intercepted.
 */
final class SpaFallback
{
    /**
     * Returns the shell path to serve, or null when the request must reach
     * the application (including when the SPA has not been built).
     */
    public static function shellPath(ServerRequestInterface $request, string $publicHtmlDir): ?string
    {
        $acceptHeader = $request->getHeaderLine('Accept');
        $wantsHtml = str_contains($acceptHeader, 'text/html')
            && !str_contains($acceptHeader, 'application/json');

        if (!$wantsHtml || $request->getMethod() !== 'GET') {
            return null;
        }

        $path = $request->getUri()->getPath() ?: '/';
        foreach (['/health', '/machine', '/assets/', '/demo/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return null;
            }
        }

        $shell = $publicHtmlDir . '/assets/index.html';

        return is_file($shell) ? $shell : null;
    }
}
