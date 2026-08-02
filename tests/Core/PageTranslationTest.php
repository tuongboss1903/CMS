<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Container;
use Core\Database;
use Core\Http\Request;
use Core\I18n\Translator;
use Core\ModuleManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Phase 13 (i18n MVP, CMS-050) - luong Admin luu ban dich vao
 * page_translations + luong Public render ban dich theo locale (fallback ve pages goc khi
 * thieu ban dich) + tinh cach ly Multi-tenant. Boot ca 2 module 'admin' va 'public' trong cung
 * 1 test (khac AdminPageBuilderTest.php chi boot 'admin') vi can kiem tra ca 2 dau luong.
 */
final class PageTranslationTest extends TestCase
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
        $moduleManager->boot($this->router, ['auth', 'user', 'role', 'dashboard', 'page', 'settings', 'media', 'admin', 'public']);
    }

    protected function tearDown(): void
    {
        Translator::setGlobalInstance(null);

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
        $this->database->statement('CREATE UNIQUE INDEX uq_pages_tenant_slug ON pages (tenant_id, slug)');
        $this->database->statement('CREATE TABLE page_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            page_id BIGINT NOT NULL,
            locale VARCHAR(10) NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            content TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )');
        $this->database->statement('CREATE UNIQUE INDEX unq_page_locale ON page_translations (page_id, locale)');
        $this->database->statement('CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT NOT NULL,
            uploaded_by BIGINT NOT NULL
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

    private function seedPage(int $tenantId, int $userId, string $slug, string $title = 'Trang goc'): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, $title, $slug, \json_encode(['text' => 'Noi dung goc tieng Viet']), 'published', $userId]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedTranslation(int $tenantId, int $pageId, string $locale, string $title, string $content): void
    {
        $this->database->insert(
            'INSERT INTO page_translations (tenant_id, page_id, locale, title, slug, content) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, $pageId, $locale, $title, $title, \json_encode(['text' => $content])]
        );
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

    // ---- Admin: luu ban dich ----

    public function testAdminCreatePageWithEnglishTranslationSavesTranslationRow(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['page.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/pages/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            [
                'title' => 'Trang moi',
                'slug' => 'trang-moi',
                'content' => ['html' => '<p>Noi dung VI</p>'],
                'translations' => ['en' => ['title' => 'New Page', 'slug' => 'new-page', 'content' => '<p>EN content</p>']],
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());

        $page = $this->database->selectOne('SELECT id FROM pages WHERE slug = ?', ['trang-moi']);
        self::assertNotNull($page);

        $translation = $this->database->selectOne(
            'SELECT * FROM page_translations WHERE page_id = ? AND locale = ?',
            [(int) $page['id'], 'en']
        );
        self::assertNotNull($translation);
        self::assertSame('New Page', $translation['title']);
        self::assertSame($siteId, (int) $translation['tenant_id']);
    }

    public function testAdminEditPageUpdatesExistingEnglishTranslation(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId, 'trang-sua');
        $this->seedTranslation($siteId, $pageId, 'en', 'Old Title', 'Old content');
        $this->actingAs($siteId, $userId, ['page.update']);

        $formPage = $this->router->dispatch(new Request('GET', "/admin/pages/{$pageId}/edit", 'example.com'));
        self::assertStringContainsString('Old Title', $formPage->getBody());
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/pages/{$pageId}",
            'example.com',
            [],
            [
                'title' => 'Trang sua',
                'slug' => 'trang-sua',
                'content' => ['html' => '<p>VI</p>'],
                'translations' => ['en' => ['title' => 'New Title', 'slug' => 'trang-sua-en', 'content' => 'New content']],
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());

        $rows = $this->database->select('SELECT * FROM page_translations WHERE page_id = ? AND locale = ?', [$pageId, 'en']);
        self::assertCount(1, $rows);
        self::assertSame('New Title', $rows[0]['title']);
    }

    // ---- Public: render ban dich + fallback ----

    public function testPublicRouteWithLocalePrefixRendersEnglishTranslation(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $pageId = $this->seedPage($siteId, $userId, 'gioi-thieu', 'Gioi thieu');
        $this->seedTranslation($siteId, $pageId, 'en', 'About Us', 'English content here');

        $response = $this->router->dispatch(new Request('GET', '/en/gioi-thieu', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('About Us', $response->getBody());
        self::assertStringContainsString('English content here', $response->getBody());
        self::assertStringNotContainsString('Noi dung goc tieng Viet', $response->getBody());
    }

    public function testPublicRouteWithoutLocaleStillRendersOriginalVietnameseContent(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $pageId = $this->seedPage($siteId, $userId, 'gioi-thieu', 'Gioi thieu');
        $this->seedTranslation($siteId, $pageId, 'en', 'About Us', 'English content here');

        $response = $this->router->dispatch(new Request('GET', '/gioi-thieu', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Noi dung goc tieng Viet', $response->getBody());
        self::assertStringNotContainsString('About Us', $response->getBody());
    }

    public function testPublicRouteFallsBackToOriginalWhenTranslationMissing(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->seedPage($siteId, $userId, 'chua-dich', 'Chua dich');

        $response = $this->router->dispatch(new Request('GET', '/en/chua-dich', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Chua dich', $response->getBody());
        self::assertStringContainsString('Noi dung goc tieng Viet', $response->getBody());
    }

    // ---- Multi-tenant isolation ----

    public function testTranslationDataIsIsolatedPerTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userA = $this->seedUser();
        $userB = $this->seedUser();

        $this->tenantManager->setCurrent($siteA, ['id' => $siteA]);
        $pageA = $this->seedPage($siteA, $userA, 'trang-chung', 'Trang A');
        $this->seedTranslation($siteA, $pageA, 'en', 'Tenant A English', 'Tenant A content');

        $this->tenantManager->setCurrent($siteB, ['id' => $siteB]);
        $this->seedPage($siteB, $userB, 'trang-chung', 'Trang B');

        $response = $this->router->dispatch(new Request('GET', '/en/trang-chung', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Trang B', $response->getBody());
        self::assertStringNotContainsString('Tenant A English', $response->getBody());
        self::assertStringNotContainsString('Tenant A content', $response->getBody());
    }
}
