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

        $response = $app->handle(new Request('GET', '/boom', 'example.com'));

        self::assertSame(500, $response->getStatusCode());

        /** @var array{message: string} $decoded */
        $decoded = \json_decode($response->getBody(), true);
        self::assertSame('boom-test', $decoded['message']);
    }

    public function testUnhandledExceptionReturns500WithGenericMessageWhenDebugFalse(): void
    {
        $app = Application::bootstrap(self::PRODUCTION_FIXTURE_PATH);

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

        $app->handle(new Request('GET', '/boom', 'example.com'));

        $logFile = self::FIXTURE_PATH . '/storage/logs/app.log';

        self::assertFileExists($logFile);
        self::assertStringContainsString('boom-test', (string) \file_get_contents($logFile));
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
}
