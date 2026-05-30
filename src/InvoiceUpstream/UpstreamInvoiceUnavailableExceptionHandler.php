<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class UpstreamInvoiceUnavailableExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof UpstreamInvoiceUnavailableException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemDetails->create(
            $request,
            'upstream-invoice-unavailable',
            'Invoice Upstream Unavailable',
            503,
            'The Invoice API is unreachable; match confirmation is blocked.',
        );
    }
}
