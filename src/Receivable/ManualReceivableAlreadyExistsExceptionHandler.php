<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Error\DomainExceptionHandlerInterface;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class ManualReceivableAlreadyExistsExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private LocalizedProblemDetailsFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof ManualReceivableAlreadyExistsException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemDetails->create($request, 'manual-receivable-already-exists', 409);
    }
}
