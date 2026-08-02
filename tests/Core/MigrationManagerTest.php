<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Database;
use Core\Database\QueryException;
use Core\Migration\MigrationException;
use Core\Migration\MigrationNotFoundException;
use Core\MigrationManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationManagerTest extends TestCase
{
    private const VALID_FIXTURE_PATH = __DIR__ . '/../Fixtures/Migrations/Valid';
    private const FAILING_FIXTURE_PATH = __DIR__ . '/../Fixtures/Migrations/Failing';
    private const MALFORMED_FIXTURE_PATH = __DIR__ . '/../Fixtures/Migrations/Malformed';

    /** @var list<string> */
    private array $tempDirsToClean = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirsToClean as $dir) {
            foreach (\glob($dir . '/*.php') ?: [] as $file) {
                @\unlink($file);
            }

            @\rmdir($dir);
        }

        $this->tempDirsToClean = [];
    }

    public function testMigrateRunsPendingMigrationsInFilenameOrder(): void
    {
        $manager = new MigrationManager($this->freshDatabase(), 'sqlite', self::VALID_FIXTURE_PATH);

        $result = $manager->migrate();

        self::assertSame(
            ['2024_01_01_000001_create_posts_table', '2024_01_01_000002_create_tags_table'],
            $result
        );
    }

    public function testMigrateActuallyAppliesSchemaChanges(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::VALID_FIXTURE_PATH);

        $manager->migrate();

        $database->statement("INSERT INTO posts (title) VALUES ('hello')");
        $post = $database->selectOne('SELECT * FROM posts WHERE title = ?', ['hello']);
        self::assertSame('hello', $post['title']);

        $database->statement("INSERT INTO tags (name) VALUES ('php')");
        $tag = $database->selectOne('SELECT * FROM tags WHERE name = ?', ['php']);
        self::assertSame('php', $tag['name']);
    }

    public function testMigrateReturnsEmptyWhenNothingPending(): void
    {
        $manager = new MigrationManager($this->freshDatabase(), 'sqlite', self::VALID_FIXTURE_PATH);

        $manager->migrate();
        $second = $manager->migrate();

        self::assertSame([], $second);
    }

    public function testMigrateRecordsBatchOneOnFirstRun(): void
    {
        $manager = new MigrationManager($this->freshDatabase(), 'sqlite', self::VALID_FIXTURE_PATH);

        $manager->migrate();
        $status = $manager->status();

        self::assertSame(1, $status[0]['batch']);
        self::assertSame(1, $status[1]['batch']);
    }

    public function testMigrateIncrementsBatchNumberAcrossSeparateRuns(): void
    {
        $tempDir = $this->createTempMigrationsDir();
        \file_put_contents(
            $tempDir . '/2024_05_01_000001_first.php',
            $this->migrationFileContent('batch_table_one')
        );

        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', $tempDir);
        $manager->migrate();

        \file_put_contents(
            $tempDir . '/2024_05_02_000001_second.php',
            $this->migrationFileContent('batch_table_two')
        );
        $manager->migrate();

        $status = $manager->status();

        self::assertSame(1, $status[0]['batch']);
        self::assertSame(2, $status[1]['batch']);
    }

    public function testStatusReflectsAppliedAndPendingMigrations(): void
    {
        $tempDir = $this->createTempMigrationsDir();
        \file_put_contents(
            $tempDir . '/2024_06_01_000001_applied.php',
            $this->migrationFileContent('status_table_applied')
        );

        $manager = new MigrationManager($this->freshDatabase(), 'sqlite', $tempDir);
        $manager->migrate();

        \file_put_contents(
            $tempDir . '/2024_06_02_000001_pending.php',
            $this->migrationFileContent('status_table_pending')
        );

        $status = $manager->status();

        self::assertCount(2, $status);
        self::assertTrue($status[0]['applied']);
        self::assertSame(1, $status[0]['batch']);
        self::assertFalse($status[1]['applied']);
        self::assertNull($status[1]['batch']);
    }

    public function testRollbackReturnsAppliedMigrationsInReverseOrder(): void
    {
        $manager = new MigrationManager($this->freshDatabase(), 'sqlite', self::VALID_FIXTURE_PATH);

        $manager->migrate();
        $result = $manager->rollback();

        self::assertSame(
            ['2024_01_01_000002_create_tags_table', '2024_01_01_000001_create_posts_table'],
            $result
        );
    }

    public function testRollbackActuallyReversesSchemaChanges(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::VALID_FIXTURE_PATH);

        $manager->migrate();
        $manager->rollback();

        $status = $manager->status();

        self::assertFalse($status[0]['applied']);
        self::assertFalse($status[1]['applied']);

        $this->expectException(QueryException::class);
        $database->statement('SELECT * FROM posts');
    }

    public function testRollbackReturnsEmptyWhenNoBatchApplied(): void
    {
        $manager = new MigrationManager($this->freshDatabase(), 'sqlite', self::VALID_FIXTURE_PATH);

        $result = $manager->rollback();

        self::assertSame([], $result);
    }

    public function testMigrateThrowsMigrationExceptionOnMalformedFile(): void
    {
        $manager = new MigrationManager($this->freshDatabase(), 'sqlite', self::MALFORMED_FIXTURE_PATH);

        $this->expectException(MigrationException::class);

        $manager->migrate();
    }

    public function testMigrateFailFastStopsAtFirstErrorAndDoesNotRunSubsequentMigrations(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::FAILING_FIXTURE_PATH);

        try {
            $manager->migrate();
            self::fail('Ky vong migrate() nem exception khi migration thu 2 loi.');
        } catch (RuntimeException $exception) {
            self::assertSame('migration-up-failure', $exception->getMessage());
        }

        $status = $manager->status();

        self::assertTrue($status[0]['applied']);
        self::assertFalse($status[1]['applied']);

        $database->statement("INSERT INTO comments (body) VALUES ('hi')");
        $row = $database->selectOne('SELECT * FROM comments');
        self::assertSame('hi', $row['body']);
    }

    public function testRollbackThrowsMigrationNotFoundExceptionWhenFileDeleted(): void
    {
        $tempDir = $this->createTempMigrationsDir();
        $file = $tempDir . '/2024_07_01_000001_deleteme.php';
        \file_put_contents($file, $this->migrationFileContent('deleteme_table'));

        $manager = new MigrationManager($this->freshDatabase(), 'sqlite', $tempDir);
        $manager->migrate();

        \unlink($file);

        $this->expectException(MigrationNotFoundException::class);

        $manager->rollback();
    }

    public function testConstructorThrowsOnUnsupportedDriver(): void
    {
        $this->expectException(MigrationException::class);

        new MigrationManager($this->freshDatabase(), 'postgres', self::VALID_FIXTURE_PATH);
    }

    public function testMigrateDoesNotAffectUnrelatedExistingTables(): void
    {
        $database = $this->freshDatabase();
        $database->statement('CREATE TABLE existing_table (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $database->insert('INSERT INTO existing_table (name) VALUES (?)', ['keep-me']);

        $manager = new MigrationManager($database, 'sqlite', self::VALID_FIXTURE_PATH);
        $manager->migrate();

        $row = $database->selectOne('SELECT * FROM existing_table WHERE name = ?', ['keep-me']);
        self::assertSame('keep-me', $row['name']);
    }

    private function freshDatabase(): Database
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        return new Database($config);
    }

    private function createTempMigrationsDir(): string
    {
        $dir = \sys_get_temp_dir() . '/cms-migrations-' . \uniqid('', true);
        \mkdir($dir, 0775, true);

        $this->tempDirsToClean[] = $dir;

        return $dir;
    }

    private function migrationFileContent(string $tableName): string
    {
        return <<<PHP
<?php
declare(strict_types=1);
use Core\Database;
return [
    'up' => static function (Database \$db): void {
        \$db->statement('CREATE TABLE {$tableName} (id INTEGER PRIMARY KEY AUTOINCREMENT)');
    },
    'down' => static function (Database \$db): void {
        \$db->statement('DROP TABLE {$tableName}');
    },
];
PHP;
    }
}
