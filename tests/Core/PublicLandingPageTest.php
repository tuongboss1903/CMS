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
 * Integration test cho Public Landing Page render qua themes/default/ THAT (khac
 * PublicPageRenderingTest.php - dung fixture theme don gian hoa, KHONG ho tro content['html']
 * qua $this->raw()). Test nay xac nhan dung theme that co the render HTML phuc tap (Hero/Feature
 * Grid/Showcase/CTA - Phase 7) ma khong bi escape loi, dung pattern REAL_THEMES_PATH da dung o
 * AdminPageManagementUiTest.php.
 */
final class PublicLandingPageTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

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
            static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default')
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

    private function seedLandingPage(int $tenantId): void
    {
        $html = '<div class="hero"><h1>Nen tang CMS Da Website cho Doanh nghiep</h1>'
            . '<p class="lead">Van hanh khong gioi han website tren cung 1 ha tang.</p>'
            . '<div class="hero-cta"><a href="/admin/login" class="btn btn-primary">Vao trang quan tri</a></div>'
            . '</div>'
            . '<div class="feature-grid">'
            . '<div class="feature-card"><h3>Multi-tenancy that</h3><p>Cach ly du lieu tuyet doi.</p></div>'
            . '<div class="feature-card"><h3>SEO Automation</h3><p>Tu dong sinh Sitemap/Robots.</p></div>'
            . '</div>'
            . '<div class="showcase-block"><div class="showcase-copy"><h2>Quan tri tap trung</h2></div></div>'
            . '<div class="cta-footer"><h2>San sang trien khai?</h2><a href="/admin/login" class="btn btn-primary">Bat dau ngay</a></div>';

        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, is_homepage, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$tenantId, 'Trang chu', 'home', \json_encode(['html' => $html]), 'published', 1]
        );
    }

    public function testLandingPageRendersHeroFeatureGridShowcaseAndCtaSections(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedLandingPage(1);

        $response = $this->router->dispatch(new Request('GET', '/', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('class="hero"', $response->getBody());
        self::assertStringContainsString('class="feature-grid"', $response->getBody());
        self::assertStringContainsString('class="showcase-block"', $response->getBody());
        self::assertStringContainsString('class="cta-footer"', $response->getBody());
    }

    public function testLandingPageDoesNotDuplicateGenericHeading(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedLandingPage(1);

        $response = $this->router->dispatch(new Request('GET', '/', 'example.com'));

        self::assertSame(1, \substr_count($response->getBody(), '<h1>'));
    }

    public function testLandingPageLinksToAdminLoginAreNotBroken(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedLandingPage(1);

        $response = $this->router->dispatch(new Request('GET', '/', 'example.com'));

        self::assertStringContainsString('href="/admin/login"', $response->getBody());
        self::assertStringNotContainsString('href="#"', $response->getBody());
        self::assertStringNotContainsString('href=""', $response->getBody());
    }

    public function testPlainTextPageStillRendersGenericHeading(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, is_homepage, created_by)
             VALUES (?, ?, ?, ?, ?, 0, 1)',
            [1, 'Gioi thieu', 'about', \json_encode(['text' => 'Noi dung don gian.']), 'published']
        );

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, \substr_count($response->getBody(), '<h1>'));
        self::assertStringContainsString('Gioi thieu', $response->getBody());
    }
}
