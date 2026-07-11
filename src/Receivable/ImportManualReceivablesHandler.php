<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final readonly class ImportManualReceivablesHandler
{
    public function __construct(
        private ImportManualReceivablesUseCaseInterface $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $file = $request->getUploadedFiles()['file'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            throw new ValidationException([new ValidationError('file', 'A CSV file upload is required.', 'required')]);
        }

        $output = $this->useCase->execute(new ImportManualReceivablesInput(
            organizationId: $this->organization->id(),
            contents: (string) $file->getStream(),
            actorUserId: AuthContext::userId($request),
        ));

        return $this->response->create([
            'created' => $output->created,
            'skipped' => $output->skipped,
            'errors' => $output->errors,
        ]);
    }
}
