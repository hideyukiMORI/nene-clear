<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class InvoiceAlreadyPaidExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof InvoiceAlreadyPaidException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemDetails->create(
            $request,
            'invoice-not-eligible-for-dunning',
            'Invoice Not Eligible for Dunning',
            422,
            'The invoice is already paid, voided, or has no outstanding balance.',
        );
    }
}
