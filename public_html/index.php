<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Nene2\Http\ResponseEmitter;
use NeneClear\Http\ApplicationFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

// Config boundary: raw env access stays here, not in application code.
if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$debug = (($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false')) === 'true';

$application = ApplicationFactory::create(debug: $debug, allowedOrigins: []);

$psr17 = new Psr17Factory();
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

$response = $application->handle($request);

(new ResponseEmitter())->emit($response);
