<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Application;
use Core\Cache;
use Core\Database;
use Core\Hook;
use Core\Http\Request;
use Core\ModuleManager;
use Core\Router;
use Core\Session;
use Core\View;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/../Fixtures/App';
    private const PRODUCTION_FIXTURE_PATH = __DIR__ . '/../Fixtures/AppProduction';

    protected function tearDown(): void
    {
        $logFile = self::FIXTURE_PATH . '/storage/logs/app.log';

        if (\is_file($logFile)) {
            @\unlink($logFile);
        }

        // Don sach thu muc storage/logs, storage duoc tu tao boi logException() trong test,
        // tranh de lai thu muc rong ngoai y muon trong fixture.
        @\rmdir(self::FIXTURE_PATH . '/storage/logs');
        @\rmdir(self::FIXTURE_PATH . '/storage');

        $cacheDir = \sys_get_temp_dir() . '/cms-app-test-cache';

        if (\is_dir($cacheDir)) {
            foreach (\glob($cacheDir . '/*') ?: [] as $file) {
                @\unlink($file);
            }

            @\rmdir($cacheDir);
        }
    }

    /**
     * Seed toi thieu sites/site_domains de TenantResolverMiddleware (CMS-030) khop domain
     * 'example.com' - khong chay MigrationManager that (giu test doc lap/nhanh).
     */
    private function seedTenant(Database $database, ?string $themeActive = null): void
    {
        $database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            plan_id BIGINT NULL,
            theme_active VARCHAR(100) NULL,
            storage_used_bytes BIGINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )');
        $database->statement('CREATE TABLE site_domains (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id BIGINT NOT NULL,
            domain VARCHAR(255) NOT NULL,
            is_primary BOOLEAN NOT NULL DEFAULT 0
        )');

        $database->insert('INSERT INTO sites (name, theme_active) VALUES (?, ?)', ['Site A', $themeActive]);
        $database->insert('INSERT INTO site_domains (site_id, domain) VALUES (1, ?)', ['example.com']);
    }

    public function testHealthRouteReturnsOkJson(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);

        $response = $app->handle(new Request('GET', '/health', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['success' => true, 'data' => ['status' => 'ok'], 'message' => '', 'errors' => []],
            \json_decode($response->getBody(), true)
        );
    }

    public function testModuleRouteIsLoadedAndReachable(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);
        $this->seedTenant($app->container()->get(Database::class));

        $response = $app->handle(new Request('GET', '/ping', 'example.com'));

        self::assertSame('pong', $response->getBody());
    }

    public function testUnknownRouteReturns404WithJsonEnvelope(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);

        $response = $app->handle(new Request('GET', '/does-not-exist', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'data' => null, 'message' => 'Not Found', 'errors' => []],
            \json_decode($response->getBody(), true)
        );
    }

    public function testWrongMethodReturns405WithJsonEnvelope(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);

        $response = $app->handle(new Request('POST', '/ping', 'example.com'));

        self::assertSame(405, $response->getStatusCode());

        /** @var array{message: string} $decoded */
        $decoded = \json_decode($response->getBody(), true);
        self::assertSame('Method Not Allowed', $decoded['message']);
    }

    public function testUnhandledExceptionReturns500WithRealMessageWhenDebugTrue(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);
        $this->seedTenant($app->container()->get(Database::class));

        $response = $app->handle(new Request('GET', '/boom', 'example.com'));

        self::assertSame(500, $response->getStatusCode());

        /** @var array{message: string} $decoded */
        $decoded = \json_decode($response->getBody(), true);
        self::assertSame('boom-test', $decoded['message']);
    }

    public function testUnhandledExceptionReturns500WithGenericMessageWhenDebugFalse(): void
    {
        $app = Application::bootstrap(self::PRODUCTION_FIXTURE_PATH);
        $this->seedTenant($app->container()->get(Database::class));

        $response = $app->handle(new Request('GET', '/boom', 'example.com'));

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('secret-internal-detail', $response->getBody());

        /** @var array{message: string} $decoded */
        $decoded = \json_decode($response->getBody(), true);
        self::assertSame('Internal Server Error', $decoded['message']);
    }

    public function testUnhandledExceptionIsLoggedToStorageLogs(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);
        $this->seedTenant($app->container()->get(Database::class));

        $app->handle(new Request('GET', '/boom', 'example.com'));

        $logFile = self::FIXTURE_PATH . '/storage/logs/app.log';

        self::assertFileExists($logFile);
        self::assertStringContainsString('boom-test', (string) \file_get_contents($logFile));
    }

    public function testExceptionLogContainsExceptionClassFileLineAndTrace(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);
        $this->seedTenant($app->container()->get(Database::class));

        $app->handle(new Request('GET', '/boom', 'example.com'));

        $logFile = self::FIXTURE_PATH . '/storage/logs/app.log';
        $content = (string) \file_get_contents($logFile);

        self::assertStringContainsString('RuntimeException', $content);
        self::assertStringContainsString('routes.php', $content);
    }

    public function testHookCallbackExceptionIsLoggedViaLogger(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);
        $app->handle(new Request('GET', '/health', 'example.com'));

        $hook = $app->container()->get(Hook::class);
        $hook->action('test.hook_boom', function (): void {
            throw new \RuntimeException('hook-boom-test');
        });
        $hook->do('test.hook_boom');

        $logFile = self::FIXTURE_PATH . '/storage/logs/app.log';
        $content = (string) \file_get_contents($logFile);

        self::assertStringContainsString('hook-boom-test', $content);
        self::assertStringContainsString('test.hook_boom', $content);
    }

    public function testViewUsesTenantThemeActiveWhenTenantResolved(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);
        $this->seedTenant($app->container()->get(Database::class), 'custom-theme');

        $app->handle(new Request('GET', '/ping', 'example.com'));

        self::assertSame('custom-theme', $this->activeThemeOf($app->container()->get(View::class)));
    }

    public function testViewFallsBackToConfigThemeWhenTenantThemeActiveIsNull(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);
        $this->seedTenant($app->container()->get(Database::class), null);

        $app->handle(new Request('GET', '/ping', 'example.com'));

        self::assertSame('default', $this->activeThemeOf($app->container()->get(View::class)));
    }

    public function testHealthRouteStillWorksWithoutTenantSeed(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);

        $response = $app->handle(new Request('GET', '/health', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
    }

    private function activeThemeOf(View $view): string
    {
        $reflection = new \ReflectionProperty(View::class, 'activeTheme');

        return (string) $reflection->getValue($view);
    }

    public function testAllCoreServicesResolveThroughContainerAfterBootstrap(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);
        $container = $app->container();

        self::assertInstanceOf(Database::class, $container->get(Database::class));
        self::assertInstanceOf(Session::class, $container->get(Session::class));
        self::assertInstanceOf(Hook::class, $container->get(Hook::class));
        self::assertInstanceOf(Cache::class, $container->get(Cache::class));
        self::assertInstanceOf(View::class, $container->get(View::class));
        self::assertInstanceOf(Router::class, $container->get(Router::class));
        self::assertInstanceOf(ModuleManager::class, $container->get(ModuleManager::class));
    }

    public function testCoreServicesAreSingletonsWithinSameApplication(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);
        $container = $app->container();

        self::assertSame($container->get(Database::class), $container->get(Database::class));
        self::assertSame($container->get(Router::class), $container->get(Router::class));
    }

    public function testBootIsIdempotentAcrossMultipleHandleCalls(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);

        $first = $app->handle(new Request('GET', '/health', 'example.com'));
        $second = $app->handle(new Request('GET', '/health', 'example.com'));

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());
    }

    public function testRouteNotFoundDoesNotWriteToLogFile(): void
    {
        $app = Application::bootstrap(self::FIXTURE_PATH);

        $response = $app->handle(new Request('GET', '/does-not-exist', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
        self::assertFileDoesNotExist(self::FIXTURE_PATH . '/storage/logs/app.log');
    }
}
