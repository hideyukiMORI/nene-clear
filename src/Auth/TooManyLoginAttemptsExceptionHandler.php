<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Error\DomainExceptionHandlerInterface;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class TooManyLoginAttemptsExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private LocalizedProblemDetailsFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof TooManyLoginAttemptsException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        assert($exception instanceof TooManyLoginAttemptsException);

        return $this->problemDetails->create(
            $request,
            'too-many-login-attempts',
            429,
            extensions: ['retry_after_seconds' => $exception->retryAfterSeconds],
        );
    }
}
