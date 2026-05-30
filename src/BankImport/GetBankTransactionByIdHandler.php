<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetBankTransactionByIdHandler
{
    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);
        $organizationId = AuthContext::organizationId($request) ?? 0;

        $transaction = $this->transactions->findById($organizationId, $id);

        if ($transaction === null) {
            throw new BankTransactionNotFoundException($id);
        }

        return $this->response->create(BankTransactionResponse::toArray($transaction));
    }
}
