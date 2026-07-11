<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final readonly class ImportBankCsvHandler
{
    public function __construct(
        private ImportBankCsvUseCaseInterface $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $body = is_array($parsedBody) ? $parsedBody : [];

        $errors = [];
        $bankAccountId = (int) ($body['bank_account_id'] ?? 0);
        if ($bankAccountId <= 0) {
            $errors[] = new ValidationError('bank_account_id', 'A bank account id is required.', 'required');
        }

        $file = $request->getUploadedFiles()['file'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            $errors[] = new ValidationError('file', 'A CSV file upload is required.', 'required');
        }

        if ($errors !== [] || !$file instanceof UploadedFileInterface) {
            throw new ValidationException($errors);
        }

        $output = $this->useCase->execute(new ImportBankCsvInput(
            organizationId: $this->organization->id(),
            bankAccountId: $bankAccountId,
            sourceFilename: $file->getClientFilename() ?? 'upload.csv',
            contents: (string) $file->getStream(),
            actorUserId: AuthContext::userId($request),
        ));

        return $this->response->create(
            [
                'bank_import_batch_id' => $output->bankImportBatchId,
                'file_hash' => $output->fileHash,
                'row_count' => $output->rowCount,
            ],
            201,
            ['Location' => '/admin/bank-import-batches/' . $output->bankImportBatchId],
        );
    }
}
