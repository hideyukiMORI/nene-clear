<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\User\User;
use NeneClear\User\UserRepositoryInterface;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $byId = [];

    private int $nextId = 1;

    /**
     * @param list<User> $seed
     */
    public function __construct(array $seed = [])
    {
        foreach ($seed as $user) {
            $this->save($user);
        }
    }

    public function findById(int $id): ?User
    {
        return $this->byId[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    public function existsByEmail(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function findAllByOrganization(?int $organizationId, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (User $user): bool => $user->organizationId === $organizationId,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(?int $organizationId): int
    {
        return count(array_filter(
            $this->byId,
            static fn (User $user): bool => $user->organizationId === $organizationId,
        ));
    }

    public function save(User $user): int
    {
        $id = $user->id ?? $this->nextId++;
        $this->byId[$id] = new User(
            email: $user->email,
            role: $user->role,
            status: $user->status,
            passwordHash: $user->passwordHash,
            organizationId: $user->organizationId,
            id: $id,
        );

        return $id;
    }

    public function delete(int $id): void
    {
        unset($this->byId[$id]);
    }
}
