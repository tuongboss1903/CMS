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
 * Integration test cho Public Page render qua themes/default/ THAT (khong dung fixture theme -
 * fixture theme khong ho tro block rendering qua closure/$this->raw(), dung pattern giong
 * PublicLandingPageTest.php - REAL_THEMES_PATH). Phase 11 (Visual Page Builder): xac nhan 6 loai
 * block MVP render dung CSS class, image block resolve dung URL that qua MediaServeController, va
 * hoi quy 100% cho content['html']/content['text'] cu (khong bi vo khi them nhanh 'blocks' moi).
 */
final class PublicPageBuilderRenderingTest extends TestCase
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

    /** @param array<string, mixed> $content */
    private function seedPage(int $tenantId, string $slug, array $content): void
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, is_homepage, created_by)
             VALUES (?, ?, ?, ?, ?, 0, 1)',
            [$tenantId, 'Trang test', $slug, \json_encode($content, JSON_UNESCAPED_UNICODE), 'published']
        );
    }

    private function seedMedia(int $tenantId, string $fileName): int
    {
        $id = $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by)
             VALUES (?, ?, ?, ?, ?, 1)',
            [$tenantId, $fileName, $fileName, 'image/png', 1024]
        );

        return (int) $id;
    }

    public function testAllSixBlockTypesRenderWithCorrectCssClasses(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'block-page', ['blocks' => [
            ['type' => 'heading', 'level' => 2, 'text' => 'Tieu de'],
            ['type' => 'paragraph', 'text' => 'Doan van ban.'],
            ['type' => 'hero', 'headline' => 'Hero Headline', 'subheadline' => 'Hero sub', 'cta_label' => 'Xem them', 'cta_url' => '/xem-them'],
            ['type' => 'feature_grid', 'items' => [
                ['icon' => '★', 'title' => 'Feature 1', 'description' => 'Mo ta 1'],
            ]],
            ['type' => 'cta', 'headline' => 'CTA Headline', 'button_label' => 'Lien he', 'button_url' => '/lien-he'],
        ]]);

        $response = $this->router->dispatch(new Request('GET', '/block-page', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<h2>Tieu de</h2>', $response->getBody());
        self::assertStringContainsString('<p>Doan van ban.</p>', $response->getBody());
        self::assertStringContainsString('class="hero"', $response->getBody());
        self::assertStringContainsString('class="feature-grid"', $response->getBody());
        self::assertStringContainsString('class="feature-card"', $response->getBody());
        self::assertStringContainsString('class="cta-footer"', $response->getBody());
    }

    public function testImageBlockResolvesToRealMediaUrl(): void
    {
        $this->tenantManager->setCurrent(1);
        $mediaId = $this->seedMedia(1, 'banner.png');
        $this->seedPage(1, 'image-page', ['blocks' => [
            ['type' => 'image', 'media_id' => $mediaId, 'alt' => 'Banner'],
        ]]);

        $response = $this->router->dispatch(new Request('GET', '/image-page', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('src="/media/banner.png"', $response->getBody());
        self::assertStringContainsString('alt="Banner"', $response->getBody());
    }

    public function testExistingHtmlContentPageStillRendersUnaffectedByBlockFeature(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'html-page', ['html' => '<div class="hero"><h1>Trang Quill cu</h1></div>']);

        $response = $this->router->dispatch(new Request('GET', '/html-page', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<div class="hero"><h1>Trang Quill cu</h1></div>', $response->getBody());
    }

    public function testExistingPlainTextContentPageStillRendersGenericHeading(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'text-page', ['text' => 'Noi dung van ban thuong.']);

        $response = $this->router->dispatch(new Request('GET', '/text-page', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, \substr_count($response->getBody(), '<h1>'));
        self::assertStringContainsString('Noi dung van ban thuong.', $response->getBody());
    }

    public function testHeadingAndParagraphBlockTextIsEscapedAgainstXss(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'xss-page', ['blocks' => [
            ['type' => 'heading', 'level' => 2, 'text' => '<script>alert(1)</script>'],
            ['type' => 'paragraph', 'text' => '<img src=x onerror=alert(1)>'],
        ]]);

        $response = $this->router->dispatch(new Request('GET', '/xss-page', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->getBody());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getBody());
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $response->getBody());
    }

    public function testPageWithEmptyBlocksArrayRendersEmptyPageBodyWithoutError(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'empty-blocks-page', ['blocks' => []]);

        $response = $this->router->dispatch(new Request('GET', '/empty-blocks-page', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('class="page-body"', $response->getBody());
    }
}
