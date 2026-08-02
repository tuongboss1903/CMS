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
 * Integration test cho Admin Menu Builder UI (modules/Admin/Menu*Controller +
 * modules/Admin/MenuItem*Controller) - cung pattern AdminPageManagementUiTest/
 * AdminMediaManagementUiTest.
 */
final class AdminMenuManagementUiTest extends TestCase
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
        $this->database->statement('CREATE TABLE menus (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            name VARCHAR(150) NOT NULL,
            location_key VARCHAR(50) NOT NULL
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_menus_tenant_location ON menus (tenant_id, location_key)');
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
        $this->database->statement('CREATE INDEX idx_menu_items_menu_id_sort ON menu_items (menu_id, sort_order)');
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

    private function seedMenu(int $siteId, string $name = 'Main', string $locationKey = 'header'): int
    {
        $this->database->insert(
            'INSERT INTO menus (tenant_id, name, location_key) VALUES (?, ?, ?)',
            [$siteId, $name, $locationKey]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedMenuItem(int $menuId, string $label = 'Item', ?int $parentId = null, string $type = 'custom', ?string $url = '/x'): int
    {
        $this->database->insert(
            'INSERT INTO menu_items (menu_id, parent_id, label, type, url) VALUES (?, ?, ?, ?, ?)',
            [$menuId, $parentId, $label, $type, $url]
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

    public function testListShowsOnlyCurrentTenantMenus(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedMenu($siteA, 'Menu A', 'header');
        $this->seedMenu($siteB, 'Menu B', 'footer');
        $this->actingAs($siteA, $this->seedUser(), ['menu.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/menus', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Menu A', $response->getBody());
        self::assertStringNotContainsString('Menu B', $response->getBody());
    }

    public function testListMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), []);

        $response = $this->router->dispatch(new Request('GET', '/admin/menus', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Create Menu ----

    public function testCreateMenuSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.create']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/menus', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/menus',
            'example.com',
            [],
            ['name' => 'Main Menu', 'location_key' => 'header', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM menus WHERE tenant_id = ? AND location_key = ?', [$siteId, 'header']);
        self::assertNotNull($row);
    }

    public function testCreateMenuDuplicateLocationKeyDoesNotCreate(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->seedMenu($siteId, 'Existing', 'header');
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.create']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/menus', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/menus',
            'example.com',
            [],
            ['name' => 'Dup', 'location_key' => 'header', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM menus WHERE tenant_id = ?', [$siteId]);
        self::assertSame(1, (int) $count['c']);
    }

    // ---- Show Menu ----

    public function testShowMenuRendersNestedTree(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId);
        $parentItemId = $this->seedMenuItem($menuId, 'Parent Item');
        $this->seedMenuItem($menuId, 'Child Item', $parentItemId);
        $this->actingAs($siteId, $userId, ['menu.view']);

        $response = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Parent Item', $response->getBody());
        self::assertStringContainsString('Child Item', $response->getBody());
    }

    public function testShowMenuCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $menuInB = $this->seedMenu($siteB);
        $this->actingAs($siteA, $this->seedUser(), ['menu.view']);

        $response = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuInB}", 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Update Menu ----

    public function testUpdateMenuSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId, 'Old Name', 'header');
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.update']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menus/{$menuId}",
            'example.com',
            [],
            ['name' => 'New Name', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT name FROM menus WHERE id = ?', [$menuId]);
        self::assertSame('New Name', $row['name']);
    }

    public function testUpdateMenuCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $menuInB = $this->seedMenu($siteB);
        $userId = $this->seedUser();
        $this->actingAs($siteA, $userId, ['menu.update']);
        $token = $this->container->get(\Core\Csrf::class)->token();

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menus/{$menuInB}",
            'example.com',
            [],
            ['name' => 'Hacked', '_token' => $token]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Delete Menu ----

    public function testDeleteMenuCascadesMenuItems(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId);
        $this->seedMenuItem($menuId, 'Item');
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.delete']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menus/{$menuId}/delete",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $menuRow = $this->database->selectOne('SELECT id FROM menus WHERE id = ?', [$menuId]);
        self::assertNull($menuRow);
        $itemCount = $this->database->selectOne('SELECT COUNT(*) as c FROM menu_items WHERE menu_id = ?', [$menuId]);
        self::assertSame(0, (int) $itemCount['c']);
    }

    // ---- Create Menu Item ----

    public function testCreateMenuItemTypePageSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId);
        $pageId = $this->seedPage($siteId);
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.update']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menus/{$menuId}/items",
            'example.com',
            [],
            ['label' => 'About', 'type' => 'page', 'reference_id' => (string) $pageId, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM menu_items WHERE menu_id = ? AND label = ?', [$menuId, 'About']);
        self::assertNotNull($row);
    }

    public function testCreateMenuItemTypePageWithInvalidReferenceIsRejected(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId);
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.update']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menus/{$menuId}/items",
            'example.com',
            [],
            ['label' => 'Bad', 'type' => 'page', 'reference_id' => '999999', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM menu_items WHERE menu_id = ?', [$menuId]);
        self::assertSame(0, (int) $count['c']);
    }

    public function testCreateMenuItemTypeCustomWithRootParentSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId);
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.update']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menus/{$menuId}/items",
            'example.com',
            [],
            ['label' => 'External', 'type' => 'custom', 'url' => 'https://example.com', 'parent_id' => '', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT parent_id FROM menu_items WHERE menu_id = ? AND label = ?', [$menuId, 'External']);
        self::assertNotNull($row);
        self::assertNull($row['parent_id']);
    }

    // ---- Update Menu Item ----

    public function testUpdateMenuItemViaFormRedirectsAndUpdatesLabel(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId);
        $itemId = $this->seedMenuItem($menuId, 'Old Label');
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.update']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menu-items/{$itemId}",
            'example.com',
            [],
            ['label' => 'New Label', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT label FROM menu_items WHERE id = ?', [$itemId]);
        self::assertSame('New Label', $row['label']);
    }

    public function testUpdateMenuItemViaAjaxReordersToRoot(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId);
        $parentId = $this->seedMenuItem($menuId, 'Parent');
        $childId = $this->seedMenuItem($menuId, 'Child', $parentId);
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.update']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menu-items/{$childId}",
            'example.com',
            [],
            ['parent_id' => '', '_token' => $token],
            ['X-REQUESTED-WITH' => 'XMLHttpRequest']
        ));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertTrue($decoded['success']);

        $row = $this->database->selectOne('SELECT parent_id FROM menu_items WHERE id = ?', [$childId]);
        self::assertNull($row['parent_id']);
    }

    public function testUpdateMenuItemSelfParentViaAjaxReturns422Json(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId);
        $itemId = $this->seedMenuItem($menuId, 'Item');
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.update']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menu-items/{$itemId}",
            'example.com',
            [],
            ['parent_id' => (string) $itemId, '_token' => $token],
            ['X-REQUESTED-WITH' => 'XMLHttpRequest']
        ));

        self::assertSame(422, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertFalse($decoded['success']);
    }

    public function testUpdateMenuItemCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $menuInB = $this->seedMenu($siteB);
        $itemInB = $this->seedMenuItem($menuInB, 'Item');
        $userId = $this->seedUser();
        $this->actingAs($siteA, $userId, ['menu.update']);
        $token = $this->container->get(\Core\Csrf::class)->token();

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menu-items/{$itemInB}",
            'example.com',
            [],
            ['label' => 'Hacked', '_token' => $token]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Delete Menu Item ----

    public function testDeleteMenuItemCascadesDescendants(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $menuId = $this->seedMenu($siteId);
        $parentId = $this->seedMenuItem($menuId, 'Parent');
        $childId = $this->seedMenuItem($menuId, 'Child', $parentId);
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.update']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/menus/{$menuId}", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/menu-items/{$parentId}/delete",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $parentRow = $this->database->selectOne('SELECT id FROM menu_items WHERE id = ?', [$parentId]);
        $childRow = $this->database->selectOne('SELECT id FROM menu_items WHERE id = ?', [$childId]);
        self::assertNull($parentRow);
        self::assertNull($childRow);
    }

    // ---- CSRF ----

    public function testCsrfFailureReturns419(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['menu.view', 'menu.create']);

        $this->router->dispatch(new Request('GET', '/admin/menus', 'example.com'));

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/menus',
            'example.com',
            [],
            ['name' => 'X', 'location_key' => 'x', '_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }
}
