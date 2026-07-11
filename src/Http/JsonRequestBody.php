<?php

declare(strict_types=1);

namespace NeneClear\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Fills the PSR-7 parsed body for `application/json` requests.
 *
 * `ServerRequestCreator::fromGlobals()` fills `parsedBody` from `$_POST`
 * (form/multipart) only. The SPA posts `application/json`, so decode it once
 * here; handlers keep reading `getParsedBody()` and serve form and JSON
 * clients alike (#262). Handlers that parse the raw body themselves
 * (`JsonRequestBodyParser`) are unaffected — the body stream stays readable.
 */
final class JsonRequestBody
{
    public static function normalize(ServerRequestInterface $request): ServerRequestInterface
    {
        if (!str_contains($request->getHeaderLine('Content-Type'), 'application/json')) {
            return $request;
        }

        $decoded = json_decode((string) $request->getBody(), associative: true);

        return is_array($decoded) ? $request->withParsedBody($decoded) : $request;
    }
}
