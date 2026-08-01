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
 * Integration test cho Module THAT (modules/Page/) - ModuleManager tro thang modules/ that,
 * Router::dispatch() that, khong fixture, cung pattern Module{Auth,User,Role}IntegrationTest.
 * Permission seed truc tiep trong test.
 */
final class ModulePageIntegrationTest extends TestCase
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
        $moduleManager->boot($this->router, ['page']);
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
        $this->database->statement('CREATE INDEX idx_pages_tenant_status ON pages (tenant_id, status)');
        $this->database->statement('CREATE INDEX idx_pages_parent_id ON pages (parent_id)');
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

    private function seedPage(int $siteId, int $userId, string $slug = 'about', string $status = 'draft'): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, status, created_by) VALUES (?, ?, ?, ?, ?)',
            [$siteId, 'Page ' . $slug, $slug, $status, $userId]
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

    // ---- Create ----

    public function testCreatePageSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['page.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/pages',
            'example.com',
            [],
            ['title' => 'About Us', 'slug' => 'about-us', 'content' => ['blocks' => ['hello']]]
        ));

        self::assertSame(201, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertTrue($decoded['success']);
        self::assertSame('draft', $decoded['data']['status']);
    }

    public function testCreatePageStoresContentAsJson(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['page.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/pages',
            'example.com',
            [],
            ['title' => 'About Us', 'slug' => 'about-us', 'content' => ['blocks' => ['hello']]]
        ));

        $decoded = \json_decode($response->getBody(), true);
        $row = $this->database->selectOne('SELECT content FROM pages WHERE id = ?', [$decoded['data']['id']]);

        self::assertSame(['blocks' => ['hello']], \json_decode($row['content'], true));
    }

    public function testCreatePageDuplicateSlugReturns422(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->seedPage($siteId, $userId, 'about-us');
        $this->actingAs($siteId, $userId, ['page.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/pages',
            'example.com',
            [],
            ['title' => 'About Us Again', 'slug' => 'about-us']
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreatePageInvalidParentReturns422(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['page.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/pages',
            'example.com',
            [],
            ['title' => 'Child', 'slug' => 'child', 'parent_id' => 999999]
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreatePageParentFromOtherTenantReturns422(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $parentInSiteB = $this->seedPage($siteB, $userId, 'parent-b');
        $this->actingAs($siteA, $userId, ['page.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/pages',
            'example.com',
            [],
            ['title' => 'Child', 'slug' => 'child', 'parent_id' => $parentInSiteB]
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    // ---- List ----

    public function testListPagesReturnsOnlyCurrentTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $this->seedPage($siteA, $userId, 'page-a');
        $this->seedPage($siteB, $userId, 'page-b');
        $this->actingAs($siteA, $userId, ['page.view']);

        $response = $this->router->dispatch(new Request('GET', '/pages', 'example.com'));

        $decoded = \json_decode($response->getBody(), true);
        self::assertCount(1, $decoded['data']);
        self::assertSame('page-a', $decoded['data'][0]['slug']);
    }

    public function testListPagesExcludesDeletedPage(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId, 'to-delete');
        $this->actingAs($siteId, $userId, ['page.view', 'page.delete']);

        $this->router->dispatch(new Request('DELETE', "/pages/{$pageId}", 'example.com'));
        $response = $this->router->dispatch(new Request('GET', '/pages', 'example.com'));

        $decoded = \json_decode($response->getBody(), true);
        self::assertCount(0, $decoded['data']);
    }

    // ---- Update ----

    public function testEditPageSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId);
        $this->actingAs($siteId, $userId, ['page.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/pages/{$pageId}",
            'example.com',
            [],
            ['title' => 'Updated Title']
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne('SELECT title FROM pages WHERE id = ?', [$pageId]);
        self::assertSame('Updated Title', $row['title']);
    }

    public function testEditCrossTenantPageReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $pageInB = $this->seedPage($siteB, $userId);
        $this->actingAs($siteA, $userId, ['page.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/pages/{$pageInB}",
            'example.com',
            [],
            ['title' => 'Hacked']
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testEditDeletedPageReturns404(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId);
        $this->actingAs($siteId, $userId, ['page.update', 'page.delete']);

        $this->router->dispatch(new Request('DELETE', "/pages/{$pageId}", 'example.com'));
        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/pages/{$pageId}",
            'example.com',
            [],
            ['title' => 'Should Fail']
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Delete ----

    public function testDeletePageSoftDeletesRow(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId);
        $this->actingAs($siteId, $userId, ['page.delete']);

        $response = $this->router->dispatch(new Request('DELETE', "/pages/{$pageId}", 'example.com'));

        self::assertSame(200, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT deleted_at FROM pages WHERE id = ?', [$pageId]);
        self::assertNotNull($row);
        self::assertNotNull($row['deleted_at']);
    }

    // ---- Publish ----

    public function testPublishPageSetsStatusAndPublishedAt(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId);
        $this->actingAs($siteId, $userId, ['page.publish']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/pages/{$pageId}/publish",
            'example.com',
            [],
            ['status' => 'published']
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne('SELECT status, published_at FROM pages WHERE id = ?', [$pageId]);
        self::assertSame('published', $row['status']);
        self::assertNotNull($row['published_at']);
    }

    public function testPublishPageInvalidStatusReturns422(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId);
        $this->actingAs($siteId, $userId, ['page.publish']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/pages/{$pageId}/publish",
            'example.com',
            [],
            ['status' => 'not-a-real-status']
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPublishTwiceDoesNotOverwritePublishedAt(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPage($siteId, $userId);
        $this->actingAs($siteId, $userId, ['page.publish']);

        $this->router->dispatch(new Request('POST', "/pages/{$pageId}/publish", 'example.com', [], ['status' => 'published']));
        $firstRow = $this->database->selectOne('SELECT published_at FROM pages WHERE id = ?', [$pageId]);

        \sleep(1);

        $this->router->dispatch(new Request('POST', "/pages/{$pageId}/publish", 'example.com', [], ['status' => 'draft']));
        $this->router->dispatch(new Request('POST', "/pages/{$pageId}/publish", 'example.com', [], ['status' => 'published']));
        $secondRow = $this->database->selectOne('SELECT published_at FROM pages WHERE id = ?', [$pageId]);

        self::assertSame($firstRow['published_at'], $secondRow['published_at']);
    }

    // ---- Homepage ----

    public function testSetHomepageClearsOldAndSetsNew(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $oldHomepage = $this->seedPage($siteId, $userId, 'old-home');
        $this->database->statement('UPDATE pages SET is_homepage = 1 WHERE id = ?', [$oldHomepage]);
        $newHomepage = $this->seedPage($siteId, $userId, 'new-home');

        $this->actingAs($siteId, $userId, ['page.update']);

        $response = $this->router->dispatch(new Request('POST', "/pages/{$newHomepage}/homepage", 'example.com'));

        self::assertSame(200, $response->getStatusCode());

        $old = $this->database->selectOne('SELECT is_homepage FROM pages WHERE id = ?', [$oldHomepage]);
        $new = $this->database->selectOne('SELECT is_homepage FROM pages WHERE id = ?', [$newHomepage]);

        self::assertSame(0, (int) $old['is_homepage']);
        self::assertSame(1, (int) $new['is_homepage']);
    }

    public function testSetHomepageDoesNotAffectOtherTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();

        $homepageB = $this->seedPage($siteB, $userId, 'home-b');
        $this->database->statement('UPDATE pages SET is_homepage = 1 WHERE id = ?', [$homepageB]);

        $pageA = $this->seedPage($siteA, $userId, 'page-a');
        $this->actingAs($siteA, $userId, ['page.update']);

        $this->router->dispatch(new Request('POST', "/pages/{$pageA}/homepage", 'example.com'));

        $stillHomepageB = $this->database->selectOne('SELECT is_homepage FROM pages WHERE id = ?', [$homepageB]);
        self::assertSame(1, (int) $stillHomepageB['is_homepage']);
    }

    // ---- Authorization ----

    public function testMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, []);

        $response = $this->router->dispatch(new Request('GET', '/pages', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }
}
