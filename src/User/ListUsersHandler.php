<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListUsersHandler
{
    public function __construct(
        private ListUsersUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $page = PaginationQueryParser::parse($request, 50, 200);
        $output = $this->useCase->execute(AuthContext::organizationId($request), $page->limit, $page->offset);

        return $this->response->create([
            'items' => array_map(UserResponse::toArray(...), $output->items),
            'limit' => $output->limit,
            'offset' => $output->offset,
            'total' => $output->total,
        ]);
    }
}
