<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ReverseBankImportBatchHandler
{
    public function __construct(
        private ReverseBankImportBatchUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $batchId = (int) ($params['id'] ?? 0);

        $body = (array) $request->getParsedBody();
        $reversalReason = is_string($body['reversal_reason'] ?? null) ? trim($body['reversal_reason']) : '';

        if ($reversalReason === '') {
            throw new ValidationException([new ValidationError('reversal_reason', 'A reversal reason is required.', 'required')]);
        }

        $output = $this->useCase->execute(new ReverseBankImportBatchInput(
            organizationId: AuthContext::organizationId($request) ?? 0,
            batchId: $batchId,
            actorUserId: AuthContext::userId($request),
            reversalReason: $reversalReason,
        ));

        return $this->response->create([
            'bank_import_batch_id' => $output->batchId,
            'rows_voided' => $output->rowsVoided,
        ]);
    }
}
