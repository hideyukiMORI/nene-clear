<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\FallbackGroupHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Builds the dedicated {@see LoggerInterface} for the demo-entry attribution
 * log ({@see DemoEntryLoggingHandler}, #324/#330): a `var/demo-entry.log`
 * JSON-lines file instead of the app's shared logger (`php://stderr`), which
 * on the Tier A shared-hosting target (HETEML) lands in the control-panel
 * error_log — not directly readable over SSH and unsuited to precise UTM
 * analysis.
 *
 * `var/` path resolution mirrors {@see FileRateLimitStorage}: the caller
 * passes the same base dir (`dirname(__DIR__, 2) . '/var'`) so the entry log
 * lives alongside the throttle state, the only writable runtime directory
 * there.
 *
 * Fail-open, matching {@see FileRateLimitStorage}'s convention: when the file
 * cannot be opened (missing/unwritable `var/`), the line falls back to PHP's
 * `error_log()` — the line still surfaces, just back where it lived before
 * this change, via a {@see FallbackGroupHandler} (Monolog forwards to the
 * next handler only if the previous one throws). The line format (JSON,
 * `message`/`context` shape) is unchanged either way — only the destination
 * moves.
 */
final readonly class DemoEntryLoggerFactory
{
    public function create(string $varDir): LoggerInterface
    {
        $formatter = new JsonFormatter();

        $file = new StreamHandler($varDir . '/demo-entry.log', Level::Info);
        $file->setFormatter($formatter);

        $fallback = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Level::Info);
        $fallback->setFormatter($formatter);

        return new Logger('nene-clear.demo-entry', [new FallbackGroupHandler([$file, $fallback])]);
    }
}
