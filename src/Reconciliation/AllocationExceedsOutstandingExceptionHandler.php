<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class AllocationExceedsOutstandingExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof AllocationExceedsOutstandingException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        assert($exception instanceof AllocationExceedsOutstandingException);

        return $this->problemDetails->create(
            $request,
            'allocation-exceeds-outstanding',
            'Allocation Exceeds Outstanding',
            422,
            'The allocation amount exceeds the invoice outstanding balance.',
            [
                'invoice_id' => $exception->invoiceId,
                'outstanding_cents' => $exception->outstandingCents,
            ],
        );
    }
}
