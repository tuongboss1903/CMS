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
use Modules\Media\MediaServeController;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Public Media Serve Route (GET /media/{filename}) - modules/Media/MediaServeController.
 * tenant_id LUON lay tu TenantManager (khong nhan tu URL - chan IDOR). storagePath override qua
 * Container::singleton() voi thu muc TEMP rieng, cung pattern ModuleMediaIntegrationTest.
 */
final class PublicMediaServeTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';

    private Container $container;
    private Router $router;
    private Database $database;
    private TenantManager $tenantManager;
    private string $storageDir;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        $this->storageDir = \sys_get_temp_dir() . '/cms_public_media_test_' . \uniqid('', true);
        \mkdir($this->storageDir, 0755, true);

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());

        $storageDir = $this->storageDir;
        $this->container->singleton(
            MediaServeController::class,
            static fn (Container $c): MediaServeController => new MediaServeController(
                $c->get(Database::class),
                $c->get(TenantManager::class),
                $storageDir
            )
        );

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['media']);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->storageDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = \scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @\unlink($path);
            }
        }

        @\rmdir($dir);
    }

    private function migrate(): void
    {
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

    private function seedMediaFile(int $tenantId, string $uniqueName, string $content, string $mimeType = 'image/png'): void
    {
        $tenantDir = $this->storageDir . '/' . $tenantId;

        if (!\is_dir($tenantDir)) {
            \mkdir($tenantDir, 0755, true);
        }

        \file_put_contents($tenantDir . '/' . $uniqueName, $content);

        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, 'original-name.png', $tenantId . '/' . $uniqueName, $mimeType, \strlen($content), 1]
        );
    }

    // ---- Serve success ----

    public function testServeReturnsFileWithCorrectContentType(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedMediaFile(1, 'photo.png', 'binary-bytes', 'image/png');

        $response = $this->router->dispatch(new Request('GET', '/media/photo.png', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaders()['Content-Type']);
        self::assertSame('binary-bytes', $response->getBody());
    }

    public function testServeReturnsCachingHeaders(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedMediaFile(1, 'photo.png', 'x');

        $response = $this->router->dispatch(new Request('GET', '/media/photo.png', 'example.com'));

        $headers = $response->getHeaders();
        self::assertArrayHasKey('ETag', $headers);
        self::assertArrayHasKey('Last-Modified', $headers);
        self::assertArrayHasKey('Cache-Control', $headers);
        self::assertStringContainsString('max-age=86400', $headers['Cache-Control']);
    }

    // ---- Tenant isolation (IDOR prevention) ----

    public function testServeCrossTenantReturns404(): void
    {
        $this->seedMediaFile(2, 'secret.png', 'tenant-2-content');
        $this->tenantManager->setCurrent(1);

        $response = $this->router->dispatch(new Request('GET', '/media/secret.png', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Not found ----

    public function testServeNonExistentFileReturns404(): void
    {
        $this->tenantManager->setCurrent(1);

        $response = $this->router->dispatch(new Request('GET', '/media/does-not-exist.png', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testServeReturns404WhenDbRowExistsButFileMissingFromDisk(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [1, 'orphan.png', '1/orphan.png', 'image/png', 10, 1]
        );

        $response = $this->router->dispatch(new Request('GET', '/media/orphan.png', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Path traversal ----

    public function testServePathTraversalAttemptReturns404(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedMediaFile(1, 'photo.png', 'x');

        $response = $this->router->dispatch(new Request('GET', '/media/..%2f..%2f..%2fetc%2fpasswd', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testServeWithOriginalFilenameInsteadOfUniqueNameReturns404(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedMediaFile(1, 'unique-name-123.png', 'x');

        $response = $this->router->dispatch(new Request('GET', '/media/original-name.png', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }
}
