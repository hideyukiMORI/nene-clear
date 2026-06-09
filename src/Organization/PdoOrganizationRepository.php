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
            'SELECT id, slug, name FROM organizations WHERE id = ? AND is_deleted = FALSE',
            [$id],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT id, slug, name FROM organizations WHERE slug = ? AND is_deleted = FALSE',
            [$slug],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function existsBySlug(string $slug): bool
    {
        return $this->query->fetchOne('SELECT 1 FROM organizations WHERE slug = ? AND is_deleted = FALSE', [$slug]) !== null;
    }

    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT id, slug, name FROM organizations WHERE is_deleted = FALSE ORDER BY id ASC LIMIT ? OFFSET ?',
            [$limit, $offset],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function countAll(): int
    {
        $row = $this->query->fetchOne('SELECT COUNT(*) AS c FROM organizations WHERE is_deleted = FALSE');

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

        return $this->query->insert(
            'INSERT INTO organizations (slug, name) VALUES (?, ?)',
            [$organization->slug, $organization->name],
        );
    }

    public function delete(int $id, string $deletedAt): void
    {
        // Soft delete — auditable tenant history is never hard-deleted.
        // `$deletedAt` comes from the use case's injected clock.
        $this->query->execute(
            'UPDATE organizations SET is_deleted = TRUE, deleted_at = ? WHERE id = ?',
            [$deletedAt, $id],
        );
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
