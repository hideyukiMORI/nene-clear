<?php

declare(strict_types=1);

use Nene2\Http\ResponseEmitter;
use NeneClear\Http\DemoAnalyticsInjection;
use NeneClear\Http\JsonRequestBody;
use NeneClear\Http\RuntimeBootstrap;
use NeneClear\Http\SpaFallback;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

// php -S with a router script routes EVERY request here, including existing
// static files (SPA assets, favicon). Returning false hands those back to the
// built-in server for direct serving — mirroring Apache's `-f` short-circuit
// in .htaccess (#268). No effect outside the CLI server.
if (PHP_SAPI === 'cli-server') {
    $staticPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_string($staticPath) && $staticPath !== '/' && is_file(__DIR__ . $staticPath)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

// Composition root (#294): configuration via Nene2 ConfigLoader/AppConfig and
// all service wiring live in NeneClear\Http\RuntimeBootstrap.
$application = RuntimeBootstrap::application(dirname(__DIR__));

$psr17 = new Psr17Factory();
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

// Fill parsedBody for application/json requests (the SPA's default; #262).
$request = JsonRequestBody::normalize($request);

// SPA fallback: browser navigation requests receive the built shell.
$spaShell = SpaFallback::shellPath($request, __DIR__);
if ($spaShell !== null) {
    // Demo-only, env-gated cookieless analytics (#324). Injection happens here,
    // server-side, so the beacon is never baked into the committed frontend
    // build or the release ZIP: when DEMO_ANALYTICS_ENDPOINT is unset (the OSS
    // default) the shell is served byte-identically with no analytics CSP.
    $analytics = DemoAnalyticsInjection::fromEnv($_ENV);
    $html = (string) file_get_contents($spaShell);

    header('Content-Type: text/html; charset=utf-8');
    $csp = $analytics->contentSecurityPolicy();
    if ($csp !== null) {
        header('Content-Security-Policy: ' . $csp);
    }

    echo $analytics->injectHead($html);
    exit;
}

(new ResponseEmitter())->emit($application->handle($request));
