<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Database\AdapterAwareTransactionManager;
use PHPUnit\Framework\TestCase;

/**
 * The PostgreSQL code path appends `RETURNING id`. SQLite (>= 3.35) supports the
 * same clause, so these tests exercise the pgsql branch on SQLite without needing
 * pdo_pgsql — the SQL the decorator emits is identical to what PostgreSQL runs.
 */
final class AdapterAwareQueryExecutorTest extends TestCase
{
    private string $dbPath;
    private DatabaseTestKit $kit;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-adapter-', true) . '.sqlite';
        $this->kit = DatabaseTestKit::sqlite($this->dbPath);
        $this->kit->queryExecutor->execute(
            'CREATE TABLE widgets (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
        );
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function testInsertReturnsGeneratedIdOnPgsqlPath(): void
    {
        $executor = new AdapterAwareQueryExecutor($this->kit->queryExecutor, 'pgsql');

        $first = $executor->insert('INSERT INTO widgets (name) VALUES (?)', ['alpha']);
        $second = $executor->insert('INSERT INTO widgets (name) VALUES (?)', ['beta']);

        self::assertSame(1, $first);
        self::assertSame(2, $second);
    }

    public function testInsertReturnsGeneratedIdOnLastInsertIdPath(): void
    {
        foreach (['sqlite', 'mysql'] as $adapter) {
            $executor = new AdapterAwareQueryExecutor($this->kit->queryExecutor, $adapter);

            $id = $executor->insert('INSERT INTO widgets (name) VALUES (?)', ["row-$adapter"]);

            self::assertGreaterThan(0, $id, "adapter $adapter should return the generated id");
        }
    }

    public function testReadAndWriteMethodsDelegateToInnerExecutor(): void
    {
        $executor = new AdapterAwareQueryExecutor($this->kit->queryExecutor, 'pgsql');
        $executor->insert('INSERT INTO widgets (name) VALUES (?)', ['gamma']);

        self::assertSame(1, $executor->execute('UPDATE widgets SET name = ? WHERE id = 1', ['renamed']));
        self::assertSame('renamed', $executor->fetchOne('SELECT name FROM widgets WHERE id = 1')['name'] ?? null);
        self::assertCount(1, $executor->fetchAll('SELECT id FROM widgets'));
    }

    public function testTransactionManagerDecoratesExecutorWithReturningId(): void
    {
        $manager = new AdapterAwareTransactionManager($this->kit->transactionManager, 'pgsql');

        $id = $manager->transactional(
            static fn (DatabaseQueryExecutorInterface $tx): int
                => $tx->insert('INSERT INTO widgets (name) VALUES (?)', ['in-tx']),
        );

        self::assertSame(1, $id);
        self::assertSame('in-tx', $this->kit->queryExecutor->fetchOne('SELECT name FROM widgets WHERE id = 1')['name'] ?? null);
    }
}
