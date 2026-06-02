<?php

declare(strict_types=1);

namespace NeneClear\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function existsByEmail(string $email): bool;

    /**
     * @return list<User>
     */
    public function findAllByOrganization(?int $organizationId, int $limit, int $offset): array;

    public function countByOrganization(?int $organizationId): int;

    public function save(User $user): int;

    public function delete(?int $organizationId, int $id, string $deletedAt): void;
}
