<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class UpstreamInvoiceNotFoundExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof UpstreamInvoiceNotFoundException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemDetails->create(
            $request,
            'upstream-invoice-not-found',
            'Invoice Not Found in Upstream',
            422,
            'The referenced invoice or client does not exist in the Invoice system.',
        );
    }
}
