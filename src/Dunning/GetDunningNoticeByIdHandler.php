<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetDunningNoticeByIdHandler
{
    public function __construct(
        private DunningNoticeRepositoryInterface $notices,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);
        $organizationId = $this->organization->id();

        $notice = $this->notices->findById($organizationId, $id);

        if ($notice === null) {
            throw new DunningNoticeNotFoundException($id);
        }

        return $this->response->create(DunningNoticeResponse::toArray($notice));
    }
}
