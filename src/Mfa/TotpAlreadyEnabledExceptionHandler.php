<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Error\DomainExceptionHandlerInterface;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class TotpAlreadyEnabledExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private LocalizedProblemDetailsFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof TotpAlreadyEnabledException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemDetails->create($request, 'totp-already-enabled', 409);
    }
}
