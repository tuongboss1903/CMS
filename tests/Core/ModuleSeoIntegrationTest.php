<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Container;
use Core\Database;
use Core\Http\Request;
use Core\ModuleManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Module Seo THAT (modules/Seo/) - ModuleManager tro thang modules/ that,
 * Router::dispatch() that, khong fixture Controller. Cung pattern ModuleMenuIntegrationTest/
 * ModuleMediaIntegrationTest.
 */
final class ModuleSeoIntegrationTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';

    private Container $container;
    private Router $router;
    private Database $database;
    private Session $session;
    private TenantManager $tenantManager;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['seo']);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    private function migrate(): void
    {
        $this->database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        $this->database->statement('CREATE TABLE pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            deleted_at TIMESTAMP NULL
        )');
        $this->database->statement('CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            file_name VARCHAR(255) NOT NULL
        )');
        $this->database->statement('CREATE TABLE seo_meta (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT NOT NULL,
            title VARCHAR(255) NULL,
            description VARCHAR(500) NULL,
            canonical VARCHAR(500) NULL,
            og_image_id BIGINT NULL,
            og_title VARCHAR(255) NULL,
            og_description VARCHAR(500) NULL,
            is_index BOOLEAN NOT NULL DEFAULT 1,
            is_follow BOOLEAN NOT NULL DEFAULT 1,
            schema_type VARCHAR(50) NULL,
            schema_data TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_seo_meta_entity ON seo_meta (tenant_id, entity_type, entity_id)');
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedPage(int $siteId, string $slug = 'about'): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug) VALUES (?, ?, ?)',
            [$siteId, 'Page ' . $slug, $slug]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedMedia(int $siteId): int
    {
        $this->database->insert('INSERT INTO media (tenant_id, file_name) VALUES (?, ?)', [$siteId, 'og.png']);

        return (int) $this->database->connection()->lastInsertId();
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.permissions', $permissions);
    }

    private function csrfToken(): string
    {
        return (new \Core\Csrf($this->session))->token();
    }

    // ---- GET ----

    public function testGetReturnsNullWhenNoMetaYet(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', "/seo/page/{$pageId}", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertTrue($decoded['success']);
        self::assertNull($decoded['data']);
    }

    public function testGetReturnsExistingMeta(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->database->insert(
            'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, title) VALUES (?, ?, ?, ?)',
            [$siteId, 'page', $pageId, 'Existing Title']
        );
        $this->actingAs($siteId, ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', "/seo/page/{$pageId}", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertSame('Existing Title', $decoded['data']['title']);
    }

    public function testGetInvalidEntityTypeReturns404(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', '/seo/post/1', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetInvalidPageReturns404(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', '/seo/page/999999', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $pageInB = $this->seedPage($siteB);
        $this->actingAs($siteA, ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', "/seo/page/{$pageInB}", 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', "/seo/page/{$pageId}", 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testGetIsolatesByTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $pageInA = $this->seedPage($siteA);
        $pageInB = $this->seedPage($siteB, 'page-b');
        $this->database->insert(
            'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, title) VALUES (?, ?, ?, ?)',
            [$siteB, 'page', $pageInB, 'Site B Title']
        );
        $this->actingAs($siteA, ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', "/seo/page/{$pageInA}", 'example.com'));

        $decoded = \json_decode($response->getBody(), true);
        self::assertNull($decoded['data']);
    }

    // ---- PATCH (upsert) ----

    public function testPatchCreatesNewMeta(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageId}",
            'example.com',
            [],
            ['title' => 'New Title', 'description' => 'New Description', '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne(
            'SELECT title, description FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );
        self::assertSame('New Title', $row['title']);
        self::assertSame('New Description', $row['description']);
    }

    public function testPatchUpdatesExistingMetaPartially(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->database->insert(
            'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, title, description) VALUES (?, ?, ?, ?, ?)',
            [$siteId, 'page', $pageId, 'Old Title', 'Old Description']
        );
        $this->actingAs($siteId, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageId}",
            'example.com',
            [],
            ['title' => 'Updated Title', '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne(
            'SELECT title, description FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );
        self::assertSame('Updated Title', $row['title']);
        self::assertSame('Old Description', $row['description']);

        $count = $this->database->selectOne(
            'SELECT COUNT(*) as c FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );
        self::assertSame(1, (int) $count['c']);
    }

    public function testPatchCreatesMetaWithDefaultIndexFollowTrue(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageId}",
            'example.com',
            [],
            ['title' => 'X', '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne(
            'SELECT is_index, is_follow FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );
        self::assertSame(1, (int) $row['is_index']);
        self::assertSame(1, (int) $row['is_follow']);
    }

    public function testPatchUpdatesOgTitleOgDescriptionAndIndexFollow(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageId}",
            'example.com',
            [],
            ['og_title' => 'OG Title', 'og_description' => 'OG Desc', 'is_index' => false, 'is_follow' => false, '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne(
            'SELECT og_title, og_description, is_index, is_follow FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );
        self::assertSame('OG Title', $row['og_title']);
        self::assertSame('OG Desc', $row['og_description']);
        self::assertSame(0, (int) $row['is_index']);
        self::assertSame(0, (int) $row['is_follow']);
    }

    public function testPatchInvalidEntityTypeReturns404(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            '/seo/post/1',
            'example.com',
            [],
            ['title' => 'x', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPatchInvalidPageReturns404(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            '/seo/page/999999',
            'example.com',
            [],
            ['title' => 'x', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPatchCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $pageInB = $this->seedPage($siteB);
        $this->actingAs($siteA, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageInB}",
            'example.com',
            [],
            ['title' => 'Hacked', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPatchWithValidOgImageSuccess(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $mediaId = $this->seedMedia($siteId);
        $this->actingAs($siteId, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageId}",
            'example.com',
            [],
            ['og_image_id' => $mediaId, '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne(
            'SELECT og_image_id FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );
        self::assertSame($mediaId, (int) $row['og_image_id']);
    }

    public function testPatchWithInvalidOgImageReturns422(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageId}",
            'example.com',
            [],
            ['og_image_id' => 999999, '_token' => $this->csrfToken()]
        ));

        self::assertSame(422, $response->getStatusCode());

        $row = $this->database->selectOne(
            'SELECT id FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );
        self::assertNull($row);
    }

    public function testPatchOgImageFromOtherTenantReturns422(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $pageInA = $this->seedPage($siteA);
        $mediaInB = $this->seedMedia($siteB);
        $this->actingAs($siteA, ['seo.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageInA}",
            'example.com',
            [],
            ['og_image_id' => $mediaInB, '_token' => $this->csrfToken()]
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPatchMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageId}",
            'example.com',
            [],
            ['title' => 'x', '_token' => $this->csrfToken()]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testSchemaDataRoundTripsAsJson(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, ['seo.update', 'seo.view']);

        $this->router->dispatch(new Request(
            'PATCH',
            "/seo/page/{$pageId}",
            'example.com',
            [],
            ['schema_type' => 'Article', 'schema_data' => ['headline' => 'Hello', 'wordCount' => 100], '_token' => $this->csrfToken()]
        ));

        $row = $this->database->selectOne(
            'SELECT schema_data FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );
        self::assertSame(['headline' => 'Hello', 'wordCount' => 100], \json_decode($row['schema_data'], true));

        $response = $this->router->dispatch(new Request('GET', "/seo/page/{$pageId}", 'example.com'));
        $decoded = \json_decode($response->getBody(), true);
        self::assertSame(['headline' => 'Hello', 'wordCount' => 100], $decoded['data']['schema_data']);
    }
}
