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
 * Integration test cho Module Settings THAT (modules/Settings/) - SiteSettingsManager, Sitemap,
 * Robots.txt. Bao gom test Route Collision (Sitemap/Robots.txt khong bi GET /{slug} cua Public
 * "nuot") - dung ModuleManager that de kiem tra dung thu tu boot theo module.json.
 */
final class ModuleSettingsIntegrationTest extends TestCase
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
            parent_id BIGINT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            content TEXT NULL,
            template VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'draft\',
            published_at TIMESTAMP NULL,
            is_homepage BOOLEAN NOT NULL DEFAULT 0,
            created_by BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
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
        $this->database->statement('CREATE TABLE site_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            site_name VARCHAR(150) NULL,
            site_tagline VARCHAR(255) NULL,
            default_meta_description VARCHAR(500) NULL,
            default_og_image_id BIGINT NULL,
            favicon_id BIGINT NULL,
            robots_txt_custom TEXT NULL
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_site_settings_tenant ON site_settings (tenant_id)');
        $this->database->statement('CREATE TABLE menus (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            name VARCHAR(150) NOT NULL,
            location_key VARCHAR(50) NOT NULL
        )');
        $this->database->statement('CREATE TABLE menu_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            menu_id BIGINT NOT NULL,
            parent_id BIGINT NULL,
            label VARCHAR(150) NOT NULL,
            type VARCHAR(20) NOT NULL,
            reference_id BIGINT NULL,
            url VARCHAR(500) NULL,
            target VARCHAR(20) NOT NULL DEFAULT \'_self\',
            sort_order INT NOT NULL DEFAULT 0
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
            schema_data TEXT NULL
        )');
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

    private function seedPage(int $siteId, string $slug, string $status = 'published'): int
    {
        $userId = $this->seedUser();

        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, status, created_by) VALUES (?, ?, ?, ?, ?)',
            [$siteId, 'Page ' . $slug, $slug, $status, $userId]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function actingAsTenant(int $siteId): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
    }

    // ---- SiteSettingsManager ----

    public function testGetReturnsDefaultsWhenNoRowExists(): void
    {
        $siteId = $this->seedSite();
        $this->actingAsTenant($siteId);

        $manager = new \Modules\Settings\SiteSettingsManager($this->database, $this->tenantManager);
        $settings = $manager->get();

        self::assertNull($settings['site_name']);
        self::assertNull($settings['robots_txt_custom']);
    }

    public function testSetCreatesThenUpdatesRow(): void
    {
        $siteId = $this->seedSite();
        $this->actingAsTenant($siteId);

        $manager = new \Modules\Settings\SiteSettingsManager($this->database, $this->tenantManager);
        $manager->set(['site_name' => 'My Site']);

        self::assertSame('My Site', $manager->get()['site_name']);

        $manager->set(['site_name' => 'Renamed Site']);
        self::assertSame('Renamed Site', $manager->get()['site_name']);

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM site_settings WHERE tenant_id = ?', [$siteId]);
        self::assertSame(1, (int) $count['c']);
    }

    public function testSetInvalidatesRuntimeCache(): void
    {
        $siteId = $this->seedSite();
        $this->actingAsTenant($siteId);

        $manager = new \Modules\Settings\SiteSettingsManager($this->database, $this->tenantManager);
        $manager->get();
        $manager->set(['site_name' => 'Updated']);

        self::assertSame('Updated', $manager->get()['site_name']);
    }

    // ---- Sitemap ----

    public function testSitemapIncludesOnlyPublishedPages(): void
    {
        $siteId = $this->seedSite();
        $this->seedPage($siteId, 'published-page', 'published');
        $this->seedPage($siteId, 'draft-page', 'draft');
        $this->actingAsTenant($siteId);

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['settings']);

        $response = $this->router->dispatch(new Request('GET', '/sitemap.xml', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/xml', $response->getHeaders()['Content-Type']);
        self::assertStringContainsString('/published-page', $response->getBody());
        self::assertStringNotContainsString('/draft-page', $response->getBody());
    }

    public function testSitemapExcludesOtherTenantPages(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedPage($siteA, 'page-a');
        $this->seedPage($siteB, 'page-b');
        $this->actingAsTenant($siteA);

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['settings']);

        $response = $this->router->dispatch(new Request('GET', '/sitemap.xml', 'example.com'));

        self::assertStringContainsString('/page-a', $response->getBody());
        self::assertStringNotContainsString('/page-b', $response->getBody());
    }

    // ---- Robots.txt ----

    public function testRobotsReturnsDefaultContentWhenNoCustomSet(): void
    {
        $siteId = $this->seedSite();
        $this->actingAsTenant($siteId);

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['settings']);

        $response = $this->router->dispatch(new Request('GET', '/robots.txt', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/plain', $response->getHeaders()['Content-Type']);
        self::assertStringContainsString('Allow: /', $response->getBody());
        self::assertStringContainsString('Sitemap:', $response->getBody());
    }

    public function testRobotsReturnsCustomContentWhenSet(): void
    {
        $siteId = $this->seedSite();
        $this->actingAsTenant($siteId);

        $manager = new \Modules\Settings\SiteSettingsManager($this->database, $this->tenantManager);
        $manager->set(['robots_txt_custom' => "User-agent: *\nDisallow: /admin"]);

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['settings']);

        $response = $this->router->dispatch(new Request('GET', '/robots.txt', 'example.com'));

        self::assertStringContainsString('Disallow: /admin', $response->getBody());
    }

    // ---- Route Collision (regression quan trong nhat cua Phase 4) ----

    public function testSitemapAndRobotsAreNotSwallowedByPublicSlugCatchAll(): void
    {
        $siteId = $this->seedSite();
        $this->seedPage($siteId, 'published-page');
        $this->actingAsTenant($siteId);

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['auth', 'user', 'role', 'dashboard', 'page', 'settings', 'media', 'public']);

        $sitemapResponse = $this->router->dispatch(new Request('GET', '/sitemap.xml', 'example.com'));
        $robotsResponse = $this->router->dispatch(new Request('GET', '/robots.txt', 'example.com'));

        self::assertSame(200, $sitemapResponse->getStatusCode());
        self::assertStringContainsString('application/xml', $sitemapResponse->getHeaders()['Content-Type']);
        self::assertSame(200, $robotsResponse->getStatusCode());
        self::assertStringContainsString('text/plain', $robotsResponse->getHeaders()['Content-Type']);
    }
}
