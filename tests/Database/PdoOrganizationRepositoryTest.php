<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Organization\Organization;
use NeneClear\Organization\PdoOrganizationRepository;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoOrganizationRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-org-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $this->query = $kit->queryExecutor;
        SchemaFixture::createOrganizations($this->query);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function test_save_then_find_by_id_and_slug(): void
    {
        $repo = new PdoOrganizationRepository($this->query);
        $id = $repo->save(new Organization(slug: 'acme', name: 'Acme Co'));

        self::assertGreaterThan(0, $id);

        $byId = $repo->findById($id);
        self::assertNotNull($byId);
        self::assertSame('acme', $byId->slug);
        self::assertSame('Acme Co', $byId->name);

        $bySlug = $repo->findBySlug('acme');
        self::assertNotNull($bySlug);
        self::assertSame($id, $bySlug->id);
    }

    public function test_exists_count_list_and_delete(): void
    {
        $repo = new PdoOrganizationRepository($this->query);
        $repo->save(new Organization(slug: 'a', name: 'A'));
        $second = $repo->save(new Organization(slug: 'b', name: 'B'));

        self::assertTrue($repo->existsBySlug('a'));
        self::assertFalse($repo->existsBySlug('zzz'));
        self::assertSame(2, $repo->countAll());
        self::assertCount(2, $repo->findAll(50, 0));

        $repo->delete($second, '2026-01-01 00:00:00');
        self::assertSame(1, $repo->countAll());
        self::assertNull($repo->findById($second));
    }

    public function test_find_by_id_returns_null_when_absent(): void
    {
        $repo = new PdoOrganizationRepository($this->query);

        self::assertNull($repo->findById(99999));
    }
}
