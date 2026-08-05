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
 * Integration test cho Phase 14 (Comment/Review System, CMS-051) - render Comment o Public site
 * (themes/default/views/pages/default.php). Dung themes/default/ THAT (khong fixture - fixture
 * theme khong co markup comment) - cung tien le PublicLandingPageTest.php.
 */
final class PublicCommentRenderingTest extends TestCase
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
        $moduleManager->boot($this->router, ['auth', 'user', 'role', 'dashboard', 'page', 'settings', 'media', 'public']);
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
        $this->database->statement('CREATE TABLE comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT NOT NULL,
            guest_name VARCHAR(150) NOT NULL,
            guest_email VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            ip_hash VARCHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )');
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

    private function seedPage(int $tenantId, string $slug, bool $isHomepage = false): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, is_homepage, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tenantId, 'Page ' . $slug, $slug, \json_encode(['text' => 'Noi dung trang']), 'published', $isHomepage ? 1 : 0, 1]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedComment(int $tenantId, int $pageId, string $status, string $guestName, string $body): void
    {
        $this->database->insert(
            'INSERT INTO comments (tenant_id, entity_type, entity_id, guest_name, guest_email, body, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tenantId, 'page', $pageId, $guestName, 'secret@example.com', $body, $status]
        );
    }

    public function testApprovedCommentsAreDisplayed(): void
    {
        $this->tenantManager->setCurrent(1);
        $pageId = $this->seedPage(1, 'bai-viet');
        $this->seedComment(1, $pageId, 'approved', 'An', 'Binh luan da duyet');

        $response = $this->router->dispatch(new Request('GET', '/bai-viet', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Binh luan da duyet', $response->getBody());
        self::assertStringContainsString('An', $response->getBody());
    }

    public function testPendingCommentsAreNotDisplayed(): void
    {
        $this->tenantManager->setCurrent(1);
        $pageId = $this->seedPage(1, 'bai-viet-2');
        $this->seedComment(1, $pageId, 'pending', 'Binh', 'Binh luan cho duyet khong hien');

        $response = $this->router->dispatch(new Request('GET', '/bai-viet-2', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Binh luan cho duyet khong hien', $response->getBody());
    }

    public function testRejectedCommentsAreNotDisplayed(): void
    {
        $this->tenantManager->setCurrent(1);
        $pageId = $this->seedPage(1, 'bai-viet-3');
        $this->seedComment(1, $pageId, 'rejected', 'Cuong', 'Binh luan bi tu choi khong hien');

        $response = $this->router->dispatch(new Request('GET', '/bai-viet-3', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Binh luan bi tu choi khong hien', $response->getBody());
    }

    public function testCommentBodyIsEscapedAgainstXss(): void
    {
        $this->tenantManager->setCurrent(1);
        $pageId = $this->seedPage(1, 'bai-viet-4');
        $this->seedComment(1, $pageId, 'approved', 'Hacker', '<script>alert(1)</script>');

        $response = $this->router->dispatch(new Request('GET', '/bai-viet-4', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->getBody());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getBody());
    }

    public function testGuestEmailIsNeverRenderedPublicly(): void
    {
        $this->tenantManager->setCurrent(1);
        $pageId = $this->seedPage(1, 'bai-viet-5');
        $this->seedComment(1, $pageId, 'approved', 'An', 'Binh luan cong khai');

        $response = $this->router->dispatch(new Request('GET', '/bai-viet-5', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('secret@example.com', $response->getBody());
    }

    public function testPageWithNoCommentsStillRendersEmptyState(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'bai-viet-6');

        $response = $this->router->dispatch(new Request('GET', '/bai-viet-6', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Chưa có bình luận nào.', $response->getBody());
    }

    public function testHomepageRendersWithoutCommentSectionCrashing(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'trang-chu', true);

        $response = $this->router->dispatch(new Request('GET', '/', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Binh luan (', $response->getBody());
    }
}
