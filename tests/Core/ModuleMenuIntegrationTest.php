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
 * Integration test cho Module Menu THAT (modules/Menu/) - ModuleManager tro thang modules/ that,
 * Router::dispatch() that, khong fixture Controller. Cung pattern ModulePageIntegrationTest/
 * ModuleMediaIntegrationTest.
 */
final class ModuleMenuIntegrationTest extends TestCase
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
        $moduleManager->boot($this->router, ['menu']);
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

    private function seedPage(int $siteId, string $slug = 'about'): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug) VALUES (?, ?, ?)',
            [$siteId, 'Page ' . $slug, $slug]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedMenu(int $siteId, string $name = 'Header', string $locationKey = 'header'): int
    {
        $this->database->insert(
            'INSERT INTO menus (tenant_id, name, location_key) VALUES (?, ?, ?)',
            [$siteId, $name, $locationKey]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedMenuItem(
        int $menuId,
        ?int $parentId = null,
        string $label = 'Item',
        string $type = 'custom',
        ?int $referenceId = null,
        ?string $url = '/link',
        int $sortOrder = 0,
    ): int {
        $this->database->insert(
            'INSERT INTO menu_items (menu_id, parent_id, label, type, reference_id, url, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$menuId, $parentId, $label, $type, $referenceId, $url, $sortOrder]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.permissions', $permissions);
    }

    private function csrfToken(): string
    {
        return (new \Core\Csrf($this->session))->token();
    }

    // ---- List / tenant isolation ----

    public function testListReturnsOnlyCurrentTenantMenus(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedMenu($siteA, 'Header A', 'header');
        $this->seedMenu($siteB, 'Header B', 'header');
        $this->actingAs($siteA, ['menu.view']);

        $response = $this->router->dispatch(new Request('GET', '/menus', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertCount(1, $decoded['data']);
        self::assertSame('Header A', $decoded['data'][0]['name']);
    }

    public function testListMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/menus', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- CRUD Menu ----

    public function testCreateMenuSuccess(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['menu.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/menus',
            'example.com',
            [],
            ['name' => 'Header', 'location_key' => 'header', '_token' => $this->csrfToken()]
        ));

        self::assertSame(201, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM menus WHERE location_key = ?', ['header']);
        self::assertNotNull($row);
    }

    public function testCreateMenuDuplicateLocationKeyReturns422(): void
    {
        $siteId = $this->seedSite();
        $this->seedMenu($siteId, 'Header', 'header');
        $this->actingAs($siteId, ['menu.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/menus',
            'example.com',
            [],
            ['name' => 'Header 2', 'location_key' => 'header', '_token' => $this->csrfToken()]
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateMenuSuccess(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId, 'Header', 'header');
        $this->actingAs($siteId, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/menus/{$menuId}",
            'example.com',
            [],
            ['name' => 'Header Renamed', '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne('SELECT name FROM menus WHERE id = ?', [$menuId]);
        self::assertSame('Header Renamed', $row['name']);
    }

    public function testUpdateMenuDuplicateLocationKeyReturns422(): void
    {
        $siteId = $this->seedSite();
        $this->seedMenu($siteId, 'Header', 'header');
        $footerId = $this->seedMenu($siteId, 'Footer', 'footer');
        $this->actingAs($siteId, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/menus/{$footerId}",
            'example.com',
            [],
            ['location_key' => 'header', '_token' => $this->csrfToken()]
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateMenuCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $menuInB = $this->seedMenu($siteB);
        $this->actingAs($siteA, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/menus/{$menuInB}",
            'example.com',
            [],
            ['name' => 'Hacked', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteMenuCascadesItems(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $itemId = $this->seedMenuItem($menuId, label: 'Home');
        $this->actingAs($siteId, ['menu.delete']);

        $response = $this->router->dispatch(new Request('DELETE', "/menus/{$menuId}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($this->database->selectOne('SELECT id FROM menus WHERE id = ?', [$menuId]));
        self::assertNull($this->database->selectOne('SELECT id FROM menu_items WHERE id = ?', [$itemId]));
    }

    public function testDeleteMenuMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('DELETE', "/menus/{$menuId}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Show Menu / Tree rendering ----

    public function testShowMenuReturnsNestedTree(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $parentId = $this->seedMenuItem($menuId, label: 'Parent', sortOrder: 0);
        $childId = $this->seedMenuItem($menuId, parentId: $parentId, label: 'Child', sortOrder: 0);
        $this->seedMenuItem($menuId, label: 'Sibling', sortOrder: 1);
        $this->actingAs($siteId, ['menu.view']);

        $response = $this->router->dispatch(new Request('GET', "/menus/{$menuId}", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        $items = $decoded['data']['items'];

        self::assertCount(2, $items);
        self::assertSame('Parent', $items[0]['label']);
        self::assertCount(1, $items[0]['children']);
        self::assertSame('Child', $items[0]['children'][0]['label']);
        self::assertSame($childId, $items[0]['children'][0]['id']);
        self::assertSame('Sibling', $items[1]['label']);
        self::assertCount(0, $items[1]['children']);
    }

    // ---- Create Menu Item ----

    public function testCreateMenuItemTypePageSuccess(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $pageId = $this->seedPage($siteId, 'about');
        $this->actingAs($siteId, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/menus/{$menuId}/items",
            'example.com',
            [],
            ['label' => 'About', 'type' => 'page', 'reference_id' => $pageId, '_token' => $this->csrfToken()]
        ));

        self::assertSame(201, $response->getStatusCode());
        $row = $this->database->selectOne('SELECT type, reference_id FROM menu_items WHERE menu_id = ?', [$menuId]);
        self::assertSame('page', $row['type']);
        self::assertSame($pageId, (int) $row['reference_id']);
    }

    public function testCreateMenuItemTypePageInvalidReferenceReturns422(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $this->actingAs($siteId, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/menus/{$menuId}/items",
            'example.com',
            [],
            ['label' => 'About', 'type' => 'page', 'reference_id' => 999999, '_token' => $this->csrfToken()]
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateMenuItemTypeCustomSuccess(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $this->actingAs($siteId, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/menus/{$menuId}/items",
            'example.com',
            [],
            ['label' => 'Google', 'type' => 'custom', 'url' => 'https://google.com', '_token' => $this->csrfToken()]
        ));

        self::assertSame(201, $response->getStatusCode());
        $row = $this->database->selectOne('SELECT type, url FROM menu_items WHERE menu_id = ?', [$menuId]);
        self::assertSame('custom', $row['type']);
        self::assertSame('https://google.com', $row['url']);
    }

    public function testCreateMenuItemTypeCustomMissingUrlReturns422(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $this->actingAs($siteId, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/menus/{$menuId}/items",
            'example.com',
            [],
            ['label' => 'Google', 'type' => 'custom', '_token' => $this->csrfToken()]
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateMenuItemOnCrossTenantMenuReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $menuInB = $this->seedMenu($siteB);
        $this->actingAs($siteA, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/menus/{$menuInB}/items",
            'example.com',
            [],
            ['label' => 'Hacked', 'type' => 'custom', 'url' => '/x', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Update Menu Item ----

    public function testUpdateMenuItemSuccess(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $itemId = $this->seedMenuItem($menuId, label: 'Old Label');
        $this->actingAs($siteId, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/menu-items/{$itemId}",
            'example.com',
            [],
            ['label' => 'New Label', 'sort_order' => 5, '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne('SELECT label, sort_order FROM menu_items WHERE id = ?', [$itemId]);
        self::assertSame('New Label', $row['label']);
        self::assertSame(5, (int) $row['sort_order']);
    }

    public function testUpdateMenuItemSelfParentReturns422(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $itemId = $this->seedMenuItem($menuId, label: 'Item');
        $this->actingAs($siteId, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/menu-items/{$itemId}",
            'example.com',
            [],
            ['parent_id' => $itemId, '_token' => $this->csrfToken()]
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateMenuItemCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $menuInB = $this->seedMenu($siteB);
        $itemInB = $this->seedMenuItem($menuInB, label: 'Item B');
        $this->actingAs($siteA, ['menu.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/menu-items/{$itemInB}",
            'example.com',
            [],
            ['label' => 'Hacked', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Delete Menu Item ----

    public function testDeleteMenuItemRemovesOnlyOwnSubtree(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $parentId = $this->seedMenuItem($menuId, label: 'Parent');
        $childId = $this->seedMenuItem($menuId, parentId: $parentId, label: 'Child');
        $siblingId = $this->seedMenuItem($menuId, label: 'Sibling');
        $this->actingAs($siteId, ['menu.update']);

        $response = $this->router->dispatch(new Request('DELETE', "/menu-items/{$parentId}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($this->database->selectOne('SELECT id FROM menu_items WHERE id = ?', [$parentId]));
        self::assertNull($this->database->selectOne('SELECT id FROM menu_items WHERE id = ?', [$childId]));
        self::assertNotNull($this->database->selectOne('SELECT id FROM menu_items WHERE id = ?', [$siblingId]));
    }

    public function testDeleteMenuItemMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $menuId = $this->seedMenu($siteId);
        $itemId = $this->seedMenuItem($menuId);
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('DELETE', "/menu-items/{$itemId}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(403, $response->getStatusCode());
    }
}
