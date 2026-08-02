<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Database;
use Core\Database\QueryException;
use Core\MigrationManager;
use PHPUnit\Framework\TestCase;

/**
 * Chay migration THAT trong database/migrations/ (khac MigrationManagerTest.php - chi test co che
 * MigrationManager qua fixture rieng). SQLite in-memory that, khong mock.
 */
final class RealMigrationsTest extends TestCase
{
    private const MIGRATIONS_PATH = __DIR__ . '/../../database/migrations';

    private const EXPECTED_ORDER = [
        '2026_08_01_000001_create_sites_table',
        '2026_08_01_000002_create_site_domains_table',
        '2026_08_01_000003_create_users_table',
        '2026_08_01_000004_create_roles_table',
        '2026_08_01_000005_create_permissions_table',
        '2026_08_01_000006_create_role_permissions_table',
        '2026_08_01_000007_create_user_site_roles_table',
        '2026_08_02_000001_create_pages_table',
        '2026_08_03_000001_create_media_table',
        '2026_08_04_000001_create_menus_table',
        '2026_08_04_000002_create_menu_items_table',
        '2026_08_05_000001_create_seo_meta_table',
        '2026_08_08_000001_alter_seo_meta_add_og_robots_fields',
        '2026_08_09_000001_create_site_settings_table',
        '2026_08_10_000001_create_analytics_views_table',
    ];

    public function testMigrateCreatesAllSevenTablesInOrder(): void
    {
        $manager = new MigrationManager($this->freshDatabase(), 'sqlite', self::MIGRATIONS_PATH);

        $result = $manager->migrate();

        self::assertSame(self::EXPECTED_ORDER, $result);
    }

    public function testEachTableHasExpectedColumns(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);
        $manager->migrate();

        $expectedColumns = [
            'sites' => ['id', 'name', 'status', 'plan_id', 'theme_active', 'storage_used_bytes', 'created_at', 'updated_at'],
            'site_domains' => ['id', 'site_id', 'domain', 'is_primary'],
            'users' => ['id', 'name', 'email', 'password', 'status', 'created_at', 'updated_at'],
            'roles' => ['id', 'tenant_id', 'name', 'is_system'],
            'permissions' => ['id', 'key', 'description'],
            'role_permissions' => ['role_id', 'permission_id'],
            'user_site_roles' => ['id', 'user_id', 'site_id', 'role_id'],
        ];

        foreach ($expectedColumns as $table => $columns) {
            $rows = $database->select("PRAGMA table_info({$table})");
            $actualColumns = \array_map(static fn (array $row): string => (string) $row['name'], $rows);

            self::assertSame($columns, $actualColumns, "Cot bang '{$table}' khong khop.");
        }
    }

    public function testUniqueConstraintsAreEnforced(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);
        $manager->migrate();

        $database->statement("INSERT INTO sites (name) VALUES ('Site A')");
        $database->statement("INSERT INTO site_domains (site_id, domain) VALUES (1, 'example.com')");

        $this->expectException(QueryException::class);
        $database->statement("INSERT INTO site_domains (site_id, domain) VALUES (1, 'example.com')");
    }

    public function testUsersEmailUniqueConstraintIsEnforced(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);
        $manager->migrate();

        $database->statement("INSERT INTO users (name, email, password) VALUES ('A', 'a@example.com', 'hash')");

        $this->expectException(QueryException::class);
        $database->statement("INSERT INTO users (name, email, password) VALUES ('B', 'a@example.com', 'hash')");
    }

    public function testRolesTenantNameUniqueConstraintIsEnforced(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);
        $manager->migrate();

        $database->statement("INSERT INTO sites (name) VALUES ('Site A')");
        $database->statement("INSERT INTO roles (tenant_id, name) VALUES (1, 'Admin')");

        $this->expectException(QueryException::class);
        $database->statement("INSERT INTO roles (tenant_id, name) VALUES (1, 'Admin')");
    }

    public function testPermissionsKeyUniqueConstraintIsEnforced(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);
        $manager->migrate();

        $database->statement("INSERT INTO permissions (`key`) VALUES ('post.create')");

        $this->expectException(QueryException::class);
        $database->statement("INSERT INTO permissions (`key`) VALUES ('post.create')");
    }

    public function testUserSiteRolesUniqueConstraintIsEnforced(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);
        $manager->migrate();

        $database->statement("INSERT INTO users (name, email, password) VALUES ('A', 'a@example.com', 'hash')");
        $database->statement("INSERT INTO sites (name) VALUES ('Site A')");
        $database->statement("INSERT INTO roles (tenant_id, name) VALUES (NULL, 'Admin')");
        $database->statement('INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (1, 1, 1)');

        $this->expectException(QueryException::class);
        $database->statement('INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (1, 1, 1)');
    }

    public function testRolePermissionsCompositePrimaryKeyRejectsDuplicateInsert(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);
        $manager->migrate();

        $database->statement("INSERT INTO roles (tenant_id, name) VALUES (NULL, 'Admin')");
        $database->statement("INSERT INTO permissions (`key`) VALUES ('post.create')");
        $database->statement('INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1)');

        $this->expectException(QueryException::class);
        $database->statement('INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1)');
    }

    public function testRollbackDropsAllTablesInReverseOrder(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);
        $manager->migrate();

        $result = $manager->rollback();

        self::assertSame(\array_reverse(self::EXPECTED_ORDER), $result);

        $status = $manager->status();
        foreach ($status as $row) {
            self::assertFalse($row['applied']);
        }
    }

    public function testMigrateIsIdempotentAfterRollback(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);

        $manager->migrate();
        $manager->rollback();
        $second = $manager->migrate();

        self::assertSame(self::EXPECTED_ORDER, $second);
    }

    public function testStatusReflectsAppliedState(): void
    {
        $database = $this->freshDatabase();
        $manager = new MigrationManager($database, 'sqlite', self::MIGRATIONS_PATH);

        $beforeMigrate = $manager->status();
        foreach ($beforeMigrate as $row) {
            self::assertFalse($row['applied']);
            self::assertNull($row['batch']);
        }

        $manager->migrate();

        $afterMigrate = $manager->status();
        foreach ($afterMigrate as $row) {
            self::assertTrue($row['applied']);
            self::assertSame(1, $row['batch']);
        }
    }

    private function freshDatabase(): Database
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        return new Database($config);
    }
}
