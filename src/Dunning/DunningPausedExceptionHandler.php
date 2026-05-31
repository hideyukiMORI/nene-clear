<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Error\DomainExceptionHandlerInterface;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DunningPausedExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(private LocalizedProblemDetailsFactory $problemDetails)
    {
    }

    public function supports(\Throwable $exception): bool
    {
        return $exception instanceof DunningPausedException;
    }

    public function handle(\Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        assert($exception instanceof DunningPausedException);

        return $this->problemDetails->create(
            $request,
            'dunning-paused',
            422,
            extensions: ['invoice_id' => $exception->invoiceId, 'paused_reason' => $exception->pausedReason],
        );
    }
}
