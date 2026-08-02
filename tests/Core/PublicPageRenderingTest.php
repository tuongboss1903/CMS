<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Container;
use Core\Database;
use Core\Http\Request;
use Core\ModuleManager;
use Core\Router;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Module Public THAT (modules/Public/) - ModuleManager tro thang modules/
 * that, Router::dispatch() that, khong fixture Controller, cung pattern
 * ModulePageIntegrationTest. View dung fixture theme rieng (tests/Fixtures/themes/test-theme) -
 * khong phu thuoc themes/default/ that (CMS-044 Decision 4).
 */
final class PublicPageRenderingTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const FIXTURE_THEMES_PATH = __DIR__ . '/../Fixtures/themes';

    private Container $container;
    private Router $router;
    private Database $database;
    private TenantManager $tenantManager;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(
            View::class,
            static fn (): View => new View(self::FIXTURE_THEMES_PATH, 'test-theme', 'test-theme')
        );

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['auth', 'user', 'role', 'dashboard', 'page', 'settings', 'media', 'public']);
    }

    private function migrate(): void
    {
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
        $this->database->statement('CREATE UNIQUE INDEX uq_pages_tenant_slug ON pages (tenant_id, slug)');
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
        $this->database->statement('CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT NOT NULL,
            uploaded_by BIGINT NOT NULL
        )');
    }

    private function seedMedia(int $tenantId, string $uniqueName = 'pic.png'): int
    {
        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, 'original.png', $tenantId . '/' . $uniqueName, 'image/png', 100, 1]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    /** @param array<string, mixed> $overrides */
    private function seedPage(int $tenantId, array $overrides = []): int
    {
        $defaults = [
            'title' => 'About Us',
            'slug' => 'about',
            'content' => \json_encode(['text' => 'hello']),
            'template' => null,
            'status' => 'published',
            'is_homepage' => 0,
            'deleted_at' => null,
        ];
        $row = [...$defaults, ...$overrides];

        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, template, status, is_homepage, created_by, deleted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [$tenantId, $row['title'], $row['slug'], $row['content'], $row['template'], $row['status'], $row['is_homepage'], $row['deleted_at']]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    public function testRenderPublishedPageBySlug(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about', 'title' => 'About Us']);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaders()['Content-Type']);
        self::assertStringContainsString('About Us', $response->getBody());
        self::assertStringContainsString('data-template="default"', $response->getBody());
    }

    public function testDraftPageReturns404(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about', 'status' => 'draft']);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeletedPageReturns404(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about', 'deleted_at' => '2026-01-01 00:00:00']);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testCrossTenantPageReturns404(): void
    {
        $this->seedPage(2, ['slug' => 'about']);
        $this->tenantManager->setCurrent(1);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testCustomTemplateIsUsedWhenExists(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about', 'template' => 'custom']);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-template="custom"', $response->getBody());
    }

    public function testTemplateFallsBackToDefaultWhenTemplateMissing(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about', 'template' => 'does-not-exist']);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-template="default"', $response->getBody());
    }

    public function testHomepageRendersPageMarkedAsHomepage(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'home', 'title' => 'Welcome', 'is_homepage' => 1]);

        $response = $this->router->dispatch(new Request('GET', '/', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Welcome', $response->getBody());
    }

    public function testHomepageReturns404WhenNoHomepageSet(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about', 'is_homepage' => 0]);

        $response = $this->router->dispatch(new Request('GET', '/', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Phase 5: SEO Header Integration ----

    public function testRobotsMetaDefaultsToIndexFollowWhenNoSeoMeta(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about']);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertStringContainsString('name="robots" content="index,follow"', $response->getBody());
    }

    public function testRobotsMetaReflectsNoindexNofollowFromSeoMeta(): void
    {
        $this->tenantManager->setCurrent(1);
        $pageId = $this->seedPage(1, ['slug' => 'about']);
        $this->database->insert(
            'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, is_index, is_follow) VALUES (?, ?, ?, ?, ?)',
            [1, 'page', $pageId, 0, 0]
        );

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertStringContainsString('name="robots" content="noindex,nofollow"', $response->getBody());
    }

    public function testFaviconLinkRendersWhenSiteSettingsHasFavicon(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about']);
        $mediaId = $this->seedMedia(1, 'favicon.png');
        $this->database->insert('INSERT INTO site_settings (tenant_id, favicon_id) VALUES (?, ?)', [1, $mediaId]);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertStringContainsString('rel="icon" href="/media/favicon.png"', $response->getBody());
    }

    public function testFaviconLinkAbsentWhenNotConfigured(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about']);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertStringNotContainsString('rel="icon"', $response->getBody());
    }

    public function testOgImageFallsBackToSiteSettingsDefaultWhenPageHasNoSeoMeta(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['slug' => 'about']);
        $mediaId = $this->seedMedia(1, 'default-og.png');
        $this->database->insert('INSERT INTO site_settings (tenant_id, default_og_image_id) VALUES (?, ?)', [1, $mediaId]);

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertStringContainsString('property="og:image" content="/media/default-og.png"', $response->getBody());
    }

    public function testBreadcrumbRendersFullAncestorChain(): void
    {
        $this->tenantManager->setCurrent(1);
        $grandparentId = $this->seedPage(1, ['slug' => 'grandparent', 'title' => 'Grandparent']);
        $this->database->statement('UPDATE pages SET parent_id = NULL WHERE id = ?', [$grandparentId]);
        $parentId = $this->seedPage(1, ['slug' => 'parent', 'title' => 'Parent']);
        $this->database->statement('UPDATE pages SET parent_id = ? WHERE id = ?', [$grandparentId, $parentId]);
        $this->seedPage(1, ['slug' => 'child', 'title' => 'Child']);
        $childId = (int) $this->database->selectOne('SELECT id FROM pages WHERE slug = ?', ['child'])['id'];
        $this->database->statement('UPDATE pages SET parent_id = ? WHERE id = ?', [$parentId, $childId]);

        $response = $this->router->dispatch(new Request('GET', '/child', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('breadcrumb', $response->getBody());
        self::assertStringContainsString('Grandparent', $response->getBody());
        self::assertStringContainsString('Parent', $response->getBody());
    }
}
