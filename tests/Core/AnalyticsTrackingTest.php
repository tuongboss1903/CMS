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
use Modules\Analytics\AnalyticsService;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Phase 12 (Advanced Analytics Dashboard, CMS-049) - AnalyticsTrackingMiddleware
 * + AnalyticsService, dung pattern giong PublicLandingPageTest.php (fixture theme don gian, khong
 * can theme that vi khong test render block). Khong tao bang "sites"/"site_domains" - dung
 * $tenantManager->setCurrent() truc tiep, khong qua TenantResolverMiddleware (dung tien le
 * PublicLandingPageTest.php).
 */
final class AnalyticsTrackingTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';

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
            static fn (): View => new View(__DIR__ . '/../Fixtures/themes', 'test-theme', 'test-theme')
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
        $this->database->statement('CREATE TABLE analytics_views (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            page_id BIGINT NULL,
            path VARCHAR(500) NOT NULL,
            ip_hash VARCHAR(64) NOT NULL,
            user_agent VARCHAR(255) NULL,
            referrer VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->statement('CREATE INDEX idx_analytics_views_tenant_created ON analytics_views (tenant_id, created_at)');
        $this->database->statement('CREATE INDEX idx_analytics_views_tenant_path ON analytics_views (tenant_id, path)');
    }

    private function seedHomepage(int $tenantId): void
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, is_homepage, created_by)
             VALUES (?, ?, ?, ?, ?, 1, 1)',
            [$tenantId, 'Trang chu', 'home', \json_encode(['text' => 'Trang chu']), 'published']
        );
    }

    private function seedPage(int $tenantId, string $slug): void
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, is_homepage, created_by)
             VALUES (?, ?, ?, ?, ?, 0, 1)',
            [$tenantId, 'Trang test', $slug, \json_encode(['text' => 'Noi dung']), 'published']
        );
    }

    public function testVisitingHomepageRecordsAnalyticsView(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedHomepage(1);

        $response = $this->router->dispatch(new Request('GET', '/', 'example.com'));

        self::assertSame(200, $response->getStatusCode());

        $rows = $this->database->select('SELECT * FROM analytics_views WHERE tenant_id = ?', [1]);
        self::assertCount(1, $rows);
        self::assertSame('/', $rows[0]['path']);
        self::assertNotNull($rows[0]['page_id']);
    }

    public function testVisitingSlugPageRecordsCorrectPageId(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'about');
        $pageId = (int) $this->database->selectOne('SELECT id FROM pages WHERE slug = ?', ['about'])['id'];

        $response = $this->router->dispatch(new Request('GET', '/about', 'example.com'));

        self::assertSame(200, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT * FROM analytics_views WHERE tenant_id = ? AND path = ?', [1, '/about']);
        self::assertNotNull($row);
        self::assertSame($pageId, (int) $row['page_id']);
    }

    public function testIpAddressIsHashedNotStoredRaw(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedHomepage(1);
        $rawIp = '203.0.113.42';

        $this->router->dispatch(new Request(
            'GET',
            '/',
            'example.com',
            [],
            [],
            ['USER-AGENT' => 'PHPUnit-Agent/1.0', 'REFERER' => 'https://google.com/search'],
            [],
            [],
            [],
            ['REMOTE_ADDR' => $rawIp]
        ));

        $row = $this->database->selectOne('SELECT * FROM analytics_views WHERE tenant_id = ?', [1]);
        self::assertNotNull($row);
        self::assertNotSame($rawIp, $row['ip_hash']);
        self::assertSame(64, \strlen((string) $row['ip_hash']));
        self::assertSame('PHPUnit-Agent/1.0', $row['user_agent']);
        self::assertSame('https://google.com/search', $row['referrer']);
    }

    public function testAnalyticsDataIsIsolatedPerTenant(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedHomepage(1);
        $this->router->dispatch(new Request('GET', '/', 'example.com'));
        $this->router->dispatch(new Request('GET', '/', 'example.com'));

        $this->tenantManager->setCurrent(2);
        $this->seedHomepage(2);
        $this->router->dispatch(new Request('GET', '/', 'example.com'));

        $analyticsService = $this->container->get(AnalyticsService::class);

        $this->tenantManager->setCurrent(1);
        self::assertSame(2, $analyticsService->totalViews('7d'));

        $this->tenantManager->setCurrent(2);
        self::assertSame(1, $analyticsService->totalViews('7d'));
    }

    public function testTrackingFailureDoesNotBreakPublicResponse(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedHomepage(1);
        $this->database->statement('DROP TABLE analytics_views');

        $response = $this->router->dispatch(new Request('GET', '/', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($response->getBody());
    }
}
