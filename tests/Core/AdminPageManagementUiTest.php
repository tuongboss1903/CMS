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
 * Integration test cho Admin Page Management UI THAT (modules/Admin/Page*Controller) - cung
 * pattern AdminUserManagementUiTest/AdminRoleManagementUiTest.
 */
final class AdminPageManagementUiTest extends TestCase
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

    private function seedPage(int $siteId, int $userId, string $slug = 'about', string $status = 'draft', int $isHomepage = 0): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, is_homepage, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$siteId, 'Page ' . $slug, $slug, \json_encode(['html' => '<p>hello</p>']), $status, $isHomepage, $userId]
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
        $userId = $this->seedUser();
        $this->seedPage($siteA, $userId, 'page-a');
        $this->seedPage($siteB, $userId, 'page-b');
        $this->actingAs($siteA, $userId, ['page.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/pages', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('page-a', $response->getBody());
        self::assertStringNotContainsString('page-b', $response->getBody());
    }

    public function testListMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/pages', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaders()['Content-Type']);
    }

    // ---- Create ----

    public function testCreatePageWithHtmlContentSuccess(): void
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
                'title' => 'About Us',
                'slug' => 'about-us',
                'content' => ['html' => '<p>Noi dung <strong>HTML</strong></p>'],
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/pages', $response->getHeaders()['Location']);

        $row = $this->database->selectOne('SELECT content FROM pages WHERE slug = ?', ['about-us']);
        self::assertNotNull($row);
        $decoded = \json_decode($row['content'], true);
        self::assertSame('<p>Noi dung <strong>HTML</strong></p>', $decoded['html']);
    }

    public function testCreatePageDuplicateSlugRendersFormAgain(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->seedPage($siteId, $userId, 'about-us');
        $this->actingAs($siteId, $userId, ['page.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/pages/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            ['title' => 'Dup', 'slug' => 'about-us', '_token' => $token]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Slug da ton tai.', $response->getBody());
    }

    public function testCreatePageMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, []);
        $token = $this->container->get(\Core\Csrf::class)->token();

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            ['title' => 'X', 'slug' => 'x', '_token' => $token]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Update ----

    public function testUpdatePageSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId, 'about');
        $this->actingAs($siteId, $userId, ['page.update']);

        $formPage = $this->router->dispatch(new Request('GET', "/admin/pages/{$pageId}/edit", 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/pages/{$pageId}",
            'example.com',
            [],
            ['title' => 'Updated Title', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT title FROM pages WHERE id = ?', [$pageId]);
        self::assertSame('Updated Title', $row['title']);
    }

    public function testUpdateCrossTenantPageReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $pageInB = $this->seedPage($siteB, $userId);
        $this->actingAs($siteA, $userId, ['page.update']);

        $response = $this->router->dispatch(new Request('GET', "/admin/pages/{$pageInB}/edit", 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Delete ----

    public function testDeletePageSoftDeletes(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId);
        $this->actingAs($siteId, $userId, ['page.delete', 'page.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/pages', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/pages/{$pageId}/delete",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT deleted_at FROM pages WHERE id = ?', [$pageId]);
        self::assertNotNull($row['deleted_at']);
    }

    // ---- Publish ----

    public function testPublishPageTogglesStatus(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId, 'about', 'draft');
        $this->actingAs($siteId, $userId, ['page.publish', 'page.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/pages', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/pages/{$pageId}/publish",
            'example.com',
            [],
            ['status' => 'published', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT status, published_at FROM pages WHERE id = ?', [$pageId]);
        self::assertSame('published', $row['status']);
        self::assertNotNull($row['published_at']);
    }

    // ---- Homepage ----

    public function testSetHomepageClearsOldAndSetsNew(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $oldHome = $this->seedPage($siteId, $userId, 'old-home', 'published', 1);
        $newHome = $this->seedPage($siteId, $userId, 'new-home', 'published', 0);
        $this->actingAs($siteId, $userId, ['page.update', 'page.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/pages', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/pages/{$newHome}/homepage",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $old = $this->database->selectOne('SELECT is_homepage FROM pages WHERE id = ?', [$oldHome]);
        $new = $this->database->selectOne('SELECT is_homepage FROM pages WHERE id = ?', [$newHome]);
        self::assertSame(0, (int) $old['is_homepage']);
        self::assertSame(1, (int) $new['is_homepage']);
    }

    // ---- CSRF ----

    public function testCsrfFailureReturns419(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['page.create']);

        $this->router->dispatch(new Request('GET', '/admin/pages/create', 'example.com'));

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            ['title' => 'X', 'slug' => 'x', '_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }
}
