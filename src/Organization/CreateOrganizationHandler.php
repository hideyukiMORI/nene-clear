<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CreateOrganizationHandler
{
    public function __construct(
        private CreateOrganizationUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $errors = [];
        $slug = trim((string) ($body['slug'] ?? ''));
        $name = trim((string) ($body['name'] ?? ''));

        if ($slug === '') {
            $errors[] = new ValidationError('slug', 'Slug is required.', 'required');
        }

        if ($name === '') {
            $errors[] = new ValidationError('name', 'Name is required.', 'required');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $output = $this->useCase->execute(new CreateOrganizationInput(slug: $slug, name: $name));

        return $this->response->create(
            ['organization_id' => $output->id, 'slug' => $output->slug, 'name' => $output->name],
            201,
            ['Location' => '/admin/organizations/' . $output->id],
        );
    }
}
