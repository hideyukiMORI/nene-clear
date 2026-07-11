<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Nene2\Http\JsonResponseFactory;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetClearSettingsHandler
{
    public function __construct(
        private ClearSettingsRepositoryInterface $settings,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $this->organization->id();
        $settings = $this->settings->findByOrganization($organizationId)
            ?? new ClearSettings($organizationId, '', '', 7);

        return $this->response->create(ClearSettingsResponse::toArray($settings));
    }
}
