<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetBankTransactionByIdHandler
{
    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);
        $organizationId = $this->organization->id();

        $transaction = $this->transactions->findById($organizationId, $id);

        if ($transaction === null) {
            throw new BankTransactionNotFoundException($id);
        }

        return $this->response->create(BankTransactionResponse::toArray($transaction));
    }
}
