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
 * Integration test cho Admin Global Site Settings UI (modules/Admin/Setting*Controller) - cung
 * pattern AdminSeoManagementUiTest. Settings la 1 ban ghi/tenant (khong co "id" rieng tren URL) -
 * khong co scenario cross-tenant 404 truyen thong, thay bang kiem tra tenant isolation qua
 * SiteSettingsManager (moi tenant chi thay/sua dung du lieu cua minh).
 */
final class AdminSettingsManagementUiTest extends TestCase
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
        $this->database->statement('CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT NOT NULL,
            uploaded_by BIGINT NOT NULL
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
        $this->database->statement('CREATE UNIQUE INDEX uq_site_settings_tenant ON site_settings (tenant_id)');
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

    private function seedMedia(int $siteId, string $mimeType = 'image/png'): int
    {
        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$siteId, 'pic.png', $siteId . '/pic.png', $mimeType, 100, 1]
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

    // ---- Show ----

    public function testShowReturnsEmptyFormWhenNoSettingsExist(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), ['settings.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/settings', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testShowMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), []);

        $response = $this->router->dispatch(new Request('GET', '/admin/settings', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Update ----

    public function testUpdateCreatesSettingsOnFirstSave(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['settings.view', 'settings.update']);

        $editPage = $this->router->dispatch(new Request('GET', '/admin/settings', 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/settings',
            'example.com',
            [],
            ['site_name' => 'My CMS Site', 'site_tagline' => 'Tagline', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT site_name, site_tagline FROM site_settings WHERE tenant_id = ?', [$siteId]);
        self::assertNotNull($row);
        self::assertSame('My CMS Site', $row['site_name']);
        self::assertSame('Tagline', $row['site_tagline']);
    }

    public function testUpdateOverwritesExistingSettings(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->database->insert('INSERT INTO site_settings (tenant_id, site_name) VALUES (?, ?)', [$siteId, 'Old Name']);
        $this->actingAs($siteId, $userId, ['settings.view', 'settings.update']);

        $editPage = $this->router->dispatch(new Request('GET', '/admin/settings', 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/settings',
            'example.com',
            [],
            ['site_name' => 'New Name', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM site_settings WHERE tenant_id = ?', [$siteId]);
        self::assertSame(1, (int) $count['c']);

        $row = $this->database->selectOne('SELECT site_name FROM site_settings WHERE tenant_id = ?', [$siteId]);
        self::assertSame('New Name', $row['site_name']);
    }

    public function testUpdateEachTenantIsolatedFromOthers(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();

        $this->actingAs($siteA, $userId, ['settings.view', 'settings.update']);
        $editPageA = $this->router->dispatch(new Request('GET', '/admin/settings', 'example.com'));
        $tokenA = $this->extractCsrfToken($editPageA->getBody());
        $this->router->dispatch(new Request('POST', '/admin/settings', 'example.com', [], ['site_name' => 'Site A Name', '_token' => $tokenA]));

        $this->actingAs($siteB, $userId, ['settings.view', 'settings.update']);
        $editPageB = $this->router->dispatch(new Request('GET', '/admin/settings', 'example.com'));
        $tokenB = $this->extractCsrfToken($editPageB->getBody());
        $this->router->dispatch(new Request('POST', '/admin/settings', 'example.com', [], ['site_name' => 'Site B Name', '_token' => $tokenB]));

        $rowA = $this->database->selectOne('SELECT site_name FROM site_settings WHERE tenant_id = ?', [$siteA]);
        $rowB = $this->database->selectOne('SELECT site_name FROM site_settings WHERE tenant_id = ?', [$siteB]);
        self::assertSame('Site A Name', $rowA['site_name']);
        self::assertSame('Site B Name', $rowB['site_name']);
    }

    public function testUpdateWithValidOgImageSavesReference(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $imageId = $this->seedMedia($siteId);
        $this->actingAs($siteId, $userId, ['settings.view', 'settings.update']);

        $editPage = $this->router->dispatch(new Request('GET', '/admin/settings', 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/settings',
            'example.com',
            [],
            ['default_og_image_id' => (string) $imageId, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT default_og_image_id FROM site_settings WHERE tenant_id = ?', [$siteId]);
        self::assertSame($imageId, (int) $row['default_og_image_id']);
    }

    public function testUpdateWithCrossTenantOgImageIsRejected(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $imageInB = $this->seedMedia($siteB);
        $this->actingAs($siteA, $userId, ['settings.view', 'settings.update']);

        $editPage = $this->router->dispatch(new Request('GET', '/admin/settings', 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/settings',
            'example.com',
            [],
            ['default_og_image_id' => (string) $imageInB, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM site_settings WHERE tenant_id = ?', [$siteA]);
        self::assertNull($row);
    }

    public function testUpdateMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, []);
        $token = $this->container->get(\Core\Csrf::class)->token();

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/settings',
            'example.com',
            [],
            ['site_name' => 'X', '_token' => $token]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- CSRF ----

    public function testCsrfFailureReturns419(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['settings.view', 'settings.update']);

        $this->router->dispatch(new Request('GET', '/admin/settings', 'example.com'));

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/settings',
            'example.com',
            [],
            ['site_name' => 'X', '_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }
}
