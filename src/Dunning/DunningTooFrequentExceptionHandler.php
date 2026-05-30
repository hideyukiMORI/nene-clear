<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Error\DomainExceptionHandlerInterface;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class DunningTooFrequentExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private LocalizedProblemDetailsFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof DunningTooFrequentException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        assert($exception instanceof DunningTooFrequentException);

        return $this->problemDetails->create(
            $request,
            'dunning-too-frequent',
            422,
            extensions: ['next_allowed_at' => $exception->nextAllowedAt],
        );
    }
}
