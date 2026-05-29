<?php

declare(strict_types=1);

namespace NeneClear\Tests\Organization;

use NeneClear\Organization\Organization;
use NeneClear\Organization\OrganizationRepositoryInterface;

final class InMemoryOrganizationRepository implements OrganizationRepositoryInterface
{
    /** @var array<int, Organization> */
    private array $byId = [];

    private int $nextId = 1;

    /**
     * @param list<Organization> $seed
     */
    public function __construct(array $seed = [])
    {
        foreach ($seed as $organization) {
            $this->save($organization);
        }
    }

    public function findById(int $id): ?Organization
    {
        return $this->byId[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        foreach ($this->byId as $organization) {
            if ($organization->slug === $slug) {
                return $organization;
            }
        }

        return null;
    }

    public function existsBySlug(string $slug): bool
    {
        return $this->findBySlug($slug) !== null;
    }

    public function findAll(int $limit, int $offset): array
    {
        return array_slice(array_values($this->byId), $offset, $limit);
    }

    public function countAll(): int
    {
        return count($this->byId);
    }

    public function save(Organization $organization): int
    {
        $id = $organization->id ?? $this->nextId++;
        $this->byId[$id] = new Organization(slug: $organization->slug, name: $organization->name, id: $id);

        return $id;
    }

    public function delete(int $id): void
    {
        unset($this->byId[$id]);
    }
}
