<?php

declare(strict_types=1);

namespace NeneClear\Organization;

interface OrganizationRepositoryInterface
{
    public function findById(int $id): ?Organization;

    public function findBySlug(string $slug): ?Organization;

    public function existsBySlug(string $slug): bool;

    /**
     * @return list<Organization>
     */
    public function findAll(int $limit, int $offset): array;

    public function countAll(): int;

    public function save(Organization $organization): int;

    public function delete(int $id, string $deletedAt): void;
}
