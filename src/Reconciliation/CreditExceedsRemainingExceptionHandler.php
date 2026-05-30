<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class CreditExceedsRemainingExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof CreditExceedsRemainingException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        assert($exception instanceof CreditExceedsRemainingException);

        return $this->problemDetails->create(
            $request,
            'credit-exceeds-remaining',
            'Credit Exceeds Remaining Balance',
            422,
            'The requested amount exceeds the remaining credit balance.',
            ['remaining_cents' => $exception->remainingCents],
        );
    }
}
