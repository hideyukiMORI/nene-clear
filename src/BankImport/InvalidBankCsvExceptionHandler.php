<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Error\DomainExceptionHandlerInterface;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class InvalidBankCsvExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private LocalizedProblemDetailsFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof InvalidBankCsvException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        $prefix = $this->problemDetails->get($request, 'problem.validation-failed.csv-prefix');
        return $this->problemDetails->createWithDetail($request, 'validation-failed', 422, $prefix . $exception->getMessage());
    }
}
