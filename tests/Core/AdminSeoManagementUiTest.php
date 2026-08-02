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
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Admin SEO Meta Settings UI (modules/Admin/Seo*Controller) - cung pattern
 * AdminPageManagementUiTest/AdminMediaManagementUiTest/AdminMenuManagementUiTest.
 */
final class AdminSeoManagementUiTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

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
        $this->container->singleton(
            View::class,
            static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default')
        );

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['auth', 'admin']);
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
        $this->database->statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        $this->database->statement('CREATE TABLE pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'draft\',
            deleted_at TIMESTAMP NULL
        )');
        $this->database->statement('CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT NOT NULL,
            uploaded_by BIGINT NOT NULL
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
            schema_type VARCHAR(50) NULL,
            schema_data TEXT NULL
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_seo_meta_entity ON seo_meta (tenant_id, entity_type, entity_id)');
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedUser(): int
    {
        $this->database->insert(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)',
            ['User', 'u' . \uniqid('', true) . '@example.com', \password_hash('x', PASSWORD_DEFAULT)]
        );

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

    private function seedMedia(int $siteId, string $mimeType = 'image/png'): int
    {
        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$siteId, 'pic.png', $siteId . '/pic.png', $mimeType, 100, 1]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedSeoMeta(int $siteId, int $pageId, string $title = 'Existing Title'): int
    {
        $this->database->insert(
            'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, title) VALUES (?, ?, ?, ?)',
            [$siteId, 'page', $pageId, $title]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, int $userId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', $userId);
        $this->session->set('auth.permissions', $permissions);
    }

    private function extractCsrfToken(string $html): string
    {
        \preg_match('/name="_token" value="([^"]+)"/', $html, $matches);

        return $matches[1] ?? '';
    }

    // ---- List ----

    public function testListShowsOnlyCurrentTenantPages(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedPage($siteA, 'page-a');
        $this->seedPage($siteB, 'page-b');
        $this->actingAs($siteA, $this->seedUser(), ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/seo', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('page-a', $response->getBody());
        self::assertStringNotContainsString('page-b', $response->getBody());
    }

    public function testListShowsConfiguredBadgeCorrectly(): void
    {
        $siteId = $this->seedSite();
        $pageWithSeo = $this->seedPage($siteId, 'with-seo');
        $this->seedPage($siteId, 'without-seo');
        $this->seedSeoMeta($siteId, $pageWithSeo);
        $this->actingAs($siteId, $this->seedUser(), ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/seo', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Da cau hinh', $response->getBody());
        self::assertStringContainsString('Chua cau hinh', $response->getBody());
    }

    public function testListMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), []);

        $response = $this->router->dispatch(new Request('GET', '/admin/seo', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Show Edit ----

    public function testShowEditForPageWithoutSeoMetaReturnsEmptyForm(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, $this->seedUser(), ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testShowEditCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $pageInB = $this->seedPage($siteB);
        $this->actingAs($siteA, $this->seedUser(), ['seo.view']);

        $response = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageInB}", 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testShowEditMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, $this->seedUser(), []);

        $response = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Update: create (insert) ----

    public function testUpdateCreatesNewSeoMeta(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, $userId, ['seo.view', 'seo.update']);

        $editPage = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageId}",
            'example.com',
            [],
            ['title' => 'New SEO Title', 'description' => 'Desc', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT title, description FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?', [$siteId, 'page', $pageId]);
        self::assertNotNull($row);
        self::assertSame('New SEO Title', $row['title']);
        self::assertSame('Desc', $row['description']);
    }

    // ---- Update: upsert (update existing) ----

    public function testUpdateOverwritesExistingSeoMeta(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId);
        $this->seedSeoMeta($siteId, $pageId, 'Old Title');
        $this->actingAs($siteId, $userId, ['seo.view', 'seo.update']);

        $editPage = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageId}",
            'example.com',
            [],
            ['title' => 'Updated Title', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?', [$siteId, 'page', $pageId]);
        self::assertSame(1, (int) $count['c']);

        $row = $this->database->selectOne('SELECT title FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?', [$siteId, 'page', $pageId]);
        self::assertSame('Updated Title', $row['title']);
    }

    // ---- OG image ----

    public function testUpdateWithValidOgImageSavesReference(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId);
        $imageId = $this->seedMedia($siteId);
        $this->actingAs($siteId, $userId, ['seo.view', 'seo.update']);

        $editPage = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageId}",
            'example.com',
            [],
            ['og_image_id' => (string) $imageId, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT og_image_id FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?', [$siteId, 'page', $pageId]);
        self::assertSame($imageId, (int) $row['og_image_id']);
    }

    public function testUpdateWithCrossTenantOgImageIsRejected(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteA);
        $imageInB = $this->seedMedia($siteB);
        $this->actingAs($siteA, $userId, ['seo.view', 'seo.update']);

        $editPage = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageId}",
            'example.com',
            [],
            ['og_image_id' => (string) $imageInB, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?', [$siteA, 'page', $pageId]);
        self::assertNull($row);
    }

    public function testUpdateWithEmptyOgImageSelectionClearsReference(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId);
        $imageId = $this->seedMedia($siteId);
        $this->actingAs($siteId, $userId, ['seo.view', 'seo.update']);

        $editPage = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageId}",
            'example.com',
            [],
            ['og_image_id' => '', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT og_image_id FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?', [$siteId, 'page', $pageId]);
        self::assertNotNull($row);
        self::assertNull($row['og_image_id']);
    }

    // ---- Schema data JSON ----

    public function testUpdateWithValidSchemaDataJsonSavesEncoded(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, $userId, ['seo.view', 'seo.update']);

        $editPage = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageId}",
            'example.com',
            [],
            ['schema_data_json' => '{"@type":"Article"}', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT schema_data FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?', [$siteId, 'page', $pageId]);
        self::assertNotNull($row);
        $decoded = \json_decode((string) $row['schema_data'], true);
        self::assertSame('Article', $decoded['@type']);
    }

    public function testUpdateWithInvalidSchemaDataJsonDoesNotSave(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, $userId, ['seo.view', 'seo.update']);

        $editPage = $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageId}",
            'example.com',
            [],
            ['schema_data_json' => '{not-valid-json', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?', [$siteId, 'page', $pageId]);
        self::assertNull($row);
    }

    // ---- Cross-tenant / permission on Update ----

    public function testUpdateCrossTenantPageReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $pageInB = $this->seedPage($siteB);
        $userId = $this->seedUser();
        $this->actingAs($siteA, $userId, ['seo.update']);
        $token = $this->container->get(\Core\Csrf::class)->token();

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageInB}",
            'example.com',
            [],
            ['title' => 'Hacked', '_token' => $token]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpdateMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, $this->seedUser(), []);
        $token = $this->container->get(\Core\Csrf::class)->token();

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageId}",
            'example.com',
            [],
            ['title' => 'X', '_token' => $token]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- CSRF ----

    public function testCsrfFailureReturns419(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, $userId, ['seo.view', 'seo.update']);

        $this->router->dispatch(new Request('GET', "/admin/seo/pages/{$pageId}", 'example.com'));

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/seo/pages/{$pageId}",
            'example.com',
            [],
            ['title' => 'X', '_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }
}
