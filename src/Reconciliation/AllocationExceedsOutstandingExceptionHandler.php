<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Error\DomainExceptionHandlerInterface;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class AllocationExceedsOutstandingExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private LocalizedProblemDetailsFactory $problemDetails,
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
            422,
            extensions: [
                'invoice_id' => $exception->invoiceId,
                'outstanding_cents' => $exception->outstandingCents,
            ],
        );
    }
}
