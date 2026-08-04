<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use Core\Cache\CacheDriver;
use Core\Cache\FileCacheDriver;
use Core\Config;
use Core\Container;
use Core\Database;
use Core\Http\Request;
use Core\ModuleManager;
use Core\PluginManager;
use Core\Router;
use Core\Session;
use Core\ThemeManager;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho SystemAdmin module (Buoc 1 Super Admin - Site/Tenant Management, Buoc 2 -
 * Module/Plugin/Theme Catalog) - cung pattern AdminUserManagementUiTest (ModuleManager tro
 * modules/ that, khong qua Application::boot() nen KHONG can TenantResolverMiddleware - dung y
 * thiet ke that su cua module nay). Cache/PluginManager/ThemeManager dang ky vao Container theo
 * dung pattern ApplicationPluginActivationIntegrationTest (FileCacheDriver tro thu muc tam rieng
 * cho tung test run, xoa sach o tearDown).
 */
final class SystemAdminSiteManagementUiTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const REAL_PLUGINS_PATH = __DIR__ . '/../../plugins';
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    private Container $container;
    private Router $router;
    private Database $database;
    private Session $session;
    private string $cachePath;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-system-admin-' . \uniqid('', true);

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(CacheDriver::class, fn (): FileCacheDriver => new FileCacheDriver($this->cachePath));
        $this->container->singleton(Cache::class, static fn (Container $c): Cache => new Cache($c->get(CacheDriver::class)));
        $this->container->singleton(ModuleManager::class, static fn (): ModuleManager => new ModuleManager(self::REAL_MODULES_PATH));
        $this->container->singleton(PluginManager::class, static fn (): PluginManager => new PluginManager(self::REAL_PLUGINS_PATH));
        $this->container->singleton(ThemeManager::class, static fn (): ThemeManager => new ThemeManager(self::REAL_THEMES_PATH));
        $this->container->singleton(
            \Core\View::class,
            static fn (): \Core\View => new \Core\View(self::REAL_THEMES_PATH, 'default', 'default')
        );

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();

        $this->migrate();

        $this->container->get(ModuleManager::class)->boot($this->router, ['system_admin']);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];

        if (\is_dir($this->cachePath)) {
            $this->removeDirectory($this->cachePath);
        }
    }

    private function removeDirectory(string $path): void
    {
        foreach (\glob($path . '/*') ?: [] as $file) {
            \is_dir($file) ? $this->removeDirectory($file) : @\unlink($file);
        }

        @\rmdir($path);
    }

    private function migrate(): void
    {
        $this->database->statement('CREATE TABLE platform_admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_platform_admins_email ON platform_admins (email)');
        $this->database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            plan_id BIGINT NULL,
            theme_active VARCHAR(100) NULL,
            storage_used_bytes BIGINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->statement('CREATE TABLE site_domains (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id BIGINT NOT NULL,
            domain VARCHAR(255) NOT NULL,
            is_primary BOOLEAN NOT NULL DEFAULT 0
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_site_domains_domain ON site_domains (domain)');
        $this->database->statement('CREATE TABLE site_plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            plugin_key VARCHAR(100) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 0,
            activated_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_site_plugins_tenant_key ON site_plugins (tenant_id, plugin_key)');
    }

    private function seedAdmin(string $email = 'root@platform.local', string $password = 'correct-password'): int
    {
        $this->database->insert(
            'INSERT INTO platform_admins (name, email, password, status) VALUES (?, ?, ?, ?)',
            ['Root', $email, \password_hash($password, PASSWORD_DEFAULT), 'active']
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function actingAsSuperAdmin(int $adminId): void
    {
        $this->session->set('system_admin.admin_id', $adminId);
    }

    private function extractCsrfToken(string $html): string
    {
        \preg_match('/name="_token" value="([^"]+)"/', $html, $matches);

        return $matches[1] ?? '';
    }

    // ---- Login ----

    public function testLoginSuccessRedirectsToSiteList(): void
    {
        $this->seedAdmin('root@platform.local', 'correct-password');

        $formPage = $this->router->dispatch(new Request('GET', '/system-admin/login', 'system-admin.local'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/system-admin/login',
            'system-admin.local',
            [],
            ['email' => 'root@platform.local', 'password' => 'correct-password', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/system-admin/sites', $response->getHeaders()['Location']);
    }

    public function testLoginWrongPasswordRendersFormAgain(): void
    {
        $this->seedAdmin('root@platform.local', 'correct-password');

        $formPage = $this->router->dispatch(new Request('GET', '/system-admin/login', 'system-admin.local'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/system-admin/login',
            'system-admin.local',
            [],
            ['email' => 'root@platform.local', 'password' => 'wrong-password', '_token' => $token]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Email hoac mat khau khong dung.', $response->getBody());
    }

    // ---- Guard ----

    public function testSiteListRedirectsToLoginWhenNotAuthenticated(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/system-admin/sites', 'system-admin.local'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/system-admin/login', $response->getHeaders()['Location']);
    }

    // ---- Site CRUD ----

    public function testCreateSiteSuccessCreatesSiteAndPrimaryDomain(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $formPage = $this->router->dispatch(new Request('GET', '/system-admin/sites/create', 'system-admin.local'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/system-admin/sites',
            'system-admin.local',
            [],
            ['name' => 'Site Moi', 'domain' => 'sitemoi.com', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/system-admin/sites', $response->getHeaders()['Location']);

        $site = $this->database->selectOne('SELECT id FROM sites WHERE name = ?', ['Site Moi']);
        self::assertNotNull($site);

        $domain = $this->database->selectOne('SELECT is_primary FROM site_domains WHERE domain = ?', ['sitemoi.com']);
        self::assertNotNull($domain);
        self::assertSame(1, (int) $domain['is_primary']);
    }

    public function testCreateSiteDuplicateDomainRendersFormAgainWithoutCreating(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site Cu']);
        $siteId = (int) $this->database->connection()->lastInsertId();
        $this->database->insert('INSERT INTO site_domains (site_id, domain, is_primary) VALUES (?, ?, 1)', [$siteId, 'daco.com']);

        $formPage = $this->router->dispatch(new Request('GET', '/system-admin/sites/create', 'system-admin.local'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/system-admin/sites',
            'system-admin.local',
            [],
            ['name' => 'Site Trung Domain', 'domain' => 'daco.com', '_token' => $token]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Domain da duoc su dung.', $response->getBody());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM sites WHERE name = ?', ['Site Trung Domain']);
        self::assertSame(0, (int) $count['c']);
    }

    public function testSuspendSiteSetsStatusSuspended(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $listPage = $this->router->dispatch(new Request('GET', '/system-admin/sites', 'system-admin.local'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/system-admin/sites/{$siteId}/suspend",
            'system-admin.local',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT status FROM sites WHERE id = ?', [$siteId]);
        self::assertSame('suspended', $row['status']);
    }

    public function testAddDomainSuccessInsertsNonPrimaryDomain(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();
        $this->database->insert('INSERT INTO site_domains (site_id, domain, is_primary) VALUES (?, ?, 1)', [$siteId, 'chinh.com']);

        $editPage = $this->router->dispatch(new Request('GET', "/system-admin/sites/{$siteId}/edit", 'system-admin.local'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/system-admin/sites/{$siteId}/domains",
            'system-admin.local',
            [],
            ['domain' => 'phu.com', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT is_primary FROM site_domains WHERE domain = ?', ['phu.com']);
        self::assertNotNull($row);
        self::assertSame(0, (int) $row['is_primary']);
    }

    public function testDeletePrimaryDomainReturns403(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();
        $this->database->insert('INSERT INTO site_domains (site_id, domain, is_primary) VALUES (?, ?, 1)', [$siteId, 'chinh.com']);
        $domainId = (int) $this->database->connection()->lastInsertId();

        $editPage = $this->router->dispatch(new Request('GET', "/system-admin/sites/{$siteId}/edit", 'system-admin.local'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/system-admin/site-domains/{$domainId}/delete",
            'system-admin.local',
            [],
            ['_token' => $token]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- CSRF ----

    public function testCsrfFailureReturns419(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->router->dispatch(new Request('GET', '/system-admin/sites/create', 'system-admin.local'));

        $response = $this->router->dispatch(new Request(
            'POST',
            '/system-admin/sites',
            'system-admin.local',
            [],
            ['name' => 'X', 'domain' => 'x.com', '_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }

    // ---- Buoc 2: Module/Plugin/Theme Catalog ----

    public function testModuleListShowsDiscoveredModules(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $response = $this->router->dispatch(new Request('GET', '/system-admin/modules', 'system-admin.local'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('system_admin', $response->getBody());
    }

    public function testThemeListShowsDiscoveredThemes(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $response = $this->router->dispatch(new Request('GET', '/system-admin/themes', 'system-admin.local'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('default', $response->getBody());
    }

    public function testUpdateSiteWithInvalidThemeRendersFormAgain(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $editPage = $this->router->dispatch(new Request('GET', "/system-admin/sites/{$siteId}/edit", 'system-admin.local'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/system-admin/sites/{$siteId}",
            'system-admin.local',
            [],
            ['name' => 'Site A', 'theme_active' => 'theme-khong-ton-tai', '_token' => $token]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Theme khong hop le.', $response->getBody());

        $row = $this->database->selectOne('SELECT theme_active FROM sites WHERE id = ?', [$siteId]);
        self::assertNull($row['theme_active']);
    }

    public function testUpdateSiteWithValidThemeSavesThemeActive(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $editPage = $this->router->dispatch(new Request('GET', "/system-admin/sites/{$siteId}/edit", 'system-admin.local'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/system-admin/sites/{$siteId}",
            'system-admin.local',
            [],
            ['name' => 'Site A', 'theme_active' => 'default', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT theme_active FROM sites WHERE id = ?', [$siteId]);
        self::assertSame('default', $row['theme_active']);
    }

    public function testSitePluginListShowsEcommercePluginInactiveByDefault(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $response = $this->router->dispatch(new Request('GET', "/system-admin/sites/{$siteId}/plugins", 'system-admin.local'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Ecommerce MVP', $response->getBody());
        self::assertStringContainsString('Dang tat', $response->getBody());
    }

    public function testSitePluginToggleActivatesThenDeactivates(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $listPage = $this->router->dispatch(new Request('GET', "/system-admin/sites/{$siteId}/plugins", 'system-admin.local'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/system-admin/sites/{$siteId}/plugins/ecommerce/toggle",
            'system-admin.local',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT is_active FROM site_plugins WHERE tenant_id = ? AND plugin_key = ?', [$siteId, 'ecommerce']);
        self::assertSame(1, (int) $row['is_active']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/system-admin/sites/{$siteId}/plugins/ecommerce/toggle",
            'system-admin.local',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT is_active FROM site_plugins WHERE tenant_id = ? AND plugin_key = ?', [$siteId, 'ecommerce']);
        self::assertSame(0, (int) $row['is_active']);
    }

    public function testSitePluginToggleUnknownKeyReturns404(): void
    {
        $adminId = $this->seedAdmin();
        $this->actingAsSuperAdmin($adminId);

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $listPage = $this->router->dispatch(new Request('GET', "/system-admin/sites/{$siteId}/plugins", 'system-admin.local'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/system-admin/sites/{$siteId}/plugins/khong-ton-tai/toggle",
            'system-admin.local',
            [],
            ['_token' => $token]
        ));

        self::assertSame(404, $response->getStatusCode());
    }
}
