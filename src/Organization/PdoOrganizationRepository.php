<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoOrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $id): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT id, slug, name FROM organizations WHERE id = ?',
            [$id],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT id, slug, name FROM organizations WHERE slug = ?',
            [$slug],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function existsBySlug(string $slug): bool
    {
        return $this->query->fetchOne('SELECT 1 FROM organizations WHERE slug = ?', [$slug]) !== null;
    }

    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT id, slug, name FROM organizations ORDER BY id ASC LIMIT ? OFFSET ?',
            [$limit, $offset],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countAll(): int
    {
        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM organizations');

        if ($row === null) {
            return 0;
        }

        return (int) ($row['c'] ?? 0);
    }

    public function save(Organization $organization): int
    {
        if ($organization->id !== null) {
            $this->query->execute(
                'UPDATE organizations SET slug = ?, name = ? WHERE id = ?',
                [$organization->slug, $organization->name, $organization->id],
            );

            return $organization->id;
        }

        $this->query->execute(
            'INSERT INTO organizations (slug, name) VALUES (?, ?)',
            [$organization->slug, $organization->name],
        );

        return $this->query->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->query->execute('DELETE FROM organizations WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Organization
    {
        return new Organization(
            slug: (string) $row['slug'],
            name: (string) $row['name'],
            id: (int) $row['id'],
        );
    }
}
