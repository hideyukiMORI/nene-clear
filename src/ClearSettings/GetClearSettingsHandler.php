<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Nene2\Http\JsonResponseFactory;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetClearSettingsHandler
{
    public function __construct(
        private ClearSettingsRepositoryInterface $settings,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $settings = $this->settings->findByOrganization($organizationId)
            ?? new ClearSettings($organizationId, '', '', 7);

        return $this->response->create(ClearSettingsResponse::toArray($settings));
    }
}
