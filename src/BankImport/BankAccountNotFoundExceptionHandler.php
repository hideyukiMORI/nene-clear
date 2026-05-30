<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Error\DomainExceptionHandlerInterface;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class BankAccountNotFoundExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private LocalizedProblemDetailsFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof BankAccountNotFoundException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemDetails->create($request, 'bank-account-not-found', 404);
    }
}
