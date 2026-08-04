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
use Modules\Media\DeleteMediaController;
use Modules\Media\UploadMediaController;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Module Media THAT (modules/Media/) - ModuleManager tro thang modules/
 * that, Router::dispatch() that, khong fixture Controller. UploadMediaController/DeleteMediaController
 * duoc override qua Container::singleton() voi thu muc storage TEMP rieng (khong ghi file that
 * vao storage/app/media cua repo) - cung pattern View (CMS-044/045).
 */
final class ModuleMediaIntegrationTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';

    /** PNG 1x1 that (magic byte hop le, base64) - can thiet vi UploadMediaController gio xac minh mime qua finfo_file(), khong con chap nhan byte gia tuy y. */
    private const REAL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private Container $container;
    private Router $router;
    private Database $database;
    private Session $session;
    private TenantManager $tenantManager;
    private string $storageDir;
    private string $sourceDir;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        $this->storageDir = \sys_get_temp_dir() . '/cms_media_test_' . \uniqid('', true);
        \mkdir($this->storageDir, 0755, true);
        $this->sourceDir = \sys_get_temp_dir() . '/cms_media_source_' . \uniqid('', true);
        \mkdir($this->sourceDir, 0755, true);

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());

        $storageDir = $this->storageDir;
        $this->container->singleton(
            UploadMediaController::class,
            static fn (Container $c): UploadMediaController => new UploadMediaController(
                $c->get(\Core\Authorization::class),
                $c->get(\Core\Auth::class),
                $c->get(Database::class),
                $c->get(TenantManager::class),
                $storageDir
            )
        );
        $this->container->singleton(
            DeleteMediaController::class,
            static fn (Container $c): DeleteMediaController => new DeleteMediaController(
                $c->get(\Core\Authorization::class),
                $c->get(Database::class),
                $c->get(TenantManager::class),
                $storageDir
            )
        );

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['media']);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];

        $this->removeDirectory($this->storageDir);
        $this->removeDirectory($this->sourceDir);
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
        $this->database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            storage_used_bytes BIGINT NOT NULL DEFAULT 0
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
            alt_text VARCHAR(255) NULL,
            title VARCHAR(255) NULL,
            caption VARCHAR(500) NULL,
            uploaded_by BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->statement('CREATE INDEX idx_media_tenant_id ON media (tenant_id)');
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

    private function seedMedia(int $siteId, string $path, int $size): int
    {
        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$siteId, 'existing.png', $path, 'image/png', $size, 1]
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

    private function csrfToken(): string
    {
        return (new \Core\Csrf($this->session))->token();
    }

    private function realPngBytes(): string
    {
        return (string) \base64_decode(self::REAL_PNG_BASE64, true);
    }

    /** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
    private function fakeUploadedFile(string $originalName, string $mimeType, string $content): array
    {
        $tmpName = $this->sourceDir . '/tmp_' . \uniqid('', true);
        \file_put_contents($tmpName, $content);

        return [
            'name' => $originalName,
            'type' => $mimeType,
            'tmp_name' => $tmpName,
            'error' => UPLOAD_ERR_OK,
            'size' => \strlen($content),
        ];
    }

    private function storageUsedBytes(int $siteId): int
    {
        $row = $this->database->selectOne('SELECT storage_used_bytes FROM sites WHERE id = ?', [$siteId]);

        return (int) $row['storage_used_bytes'];
    }

    // ---- List ----

    public function testListReturnsOnlyCurrentTenantMedia(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedMedia($siteA, "{$siteA}/a.png", 100);
        $this->seedMedia($siteB, "{$siteB}/b.png", 100);
        $this->actingAs($siteA, $this->seedUser(), ['media.view']);

        $response = $this->router->dispatch(new Request('GET', '/media', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertCount(1, $decoded['data']);
        self::assertSame("{$siteA}/a.png", $decoded['data'][0]['path']);
    }

    public function testListMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), []);

        $response = $this->router->dispatch(new Request('GET', '/media', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Upload ----

    public function testUploadSuccessCreatesRowFileAndIncreasesStorageUsed(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['media.upload']);

        $pngBytes = $this->realPngBytes();
        $file = $this->fakeUploadedFile('photo.png', 'image/png', $pngBytes);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/media',
            'example.com',
            [],
            ['_token' => $this->csrfToken()],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(201, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertTrue($decoded['success']);
        self::assertSame('photo.png', $decoded['data']['file_name']);
        self::assertSame(\strlen($pngBytes), $decoded['data']['size']);

        $row = $this->database->selectOne('SELECT * FROM media WHERE id = ?', [$decoded['data']['id']]);
        self::assertNotNull($row);
        self::assertSame($siteId, (int) $row['tenant_id']);

        $fullPath = $this->storageDir . '/' . $decoded['data']['path'];
        self::assertFileExists($fullPath);
        self::assertSame($pngBytes, \file_get_contents($fullPath));

        self::assertSame(\strlen($pngBytes), $this->storageUsedBytes($siteId));
    }

    public function testUploadMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), []);

        $file = $this->fakeUploadedFile('photo.png', 'image/png', 'x');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/media',
            'example.com',
            [],
            ['_token' => $this->csrfToken()],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUploadInvalidMimeIsRejected(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), ['media.upload']);

        $file = $this->fakeUploadedFile('script.exe', 'application/x-msdownload', 'x');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/media',
            'example.com',
            [],
            ['_token' => $this->csrfToken()],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(422, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM media WHERE tenant_id = ?', [$siteId]);
        self::assertSame(0, (int) $count['c']);
    }

    /**
     * Content-Type khai bao "image/png" nhung noi dung that la text/plain (khong phai magic byte
     * PNG hop le) - phai bi tu choi qua finfo_file(), khong duoc tin $file['type'] client tu khai.
     */
    public function testUploadRejectsContentThatDoesNotMatchClaimedMimeType(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), ['media.upload']);

        $file = $this->fakeUploadedFile('fake.png', 'image/png', 'this is plain text, not a real png');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/media',
            'example.com',
            [],
            ['_token' => $this->csrfToken()],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(422, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM media WHERE tenant_id = ?', [$siteId]);
        self::assertSame(0, (int) $count['c']);
    }

    public function testUploadOverSizeLimitIsRejected(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), ['media.upload']);

        $file = $this->fakeUploadedFile('big.png', 'image/png', 'x');
        $file['size'] = 5 * 1024 * 1024 + 1;

        $response = $this->router->dispatch(new Request(
            'POST',
            '/media',
            'example.com',
            [],
            ['_token' => $this->csrfToken()],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(422, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM media WHERE tenant_id = ?', [$siteId]);
        self::assertSame(0, (int) $count['c']);
    }

    // ---- Update ----

    public function testUpdateMetadataSuccess(): void
    {
        $siteId = $this->seedSite();
        $mediaId = $this->seedMedia($siteId, "{$siteId}/x.png", 100);
        $this->actingAs($siteId, $this->seedUser(), ['media.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/media/{$mediaId}",
            'example.com',
            [],
            ['alt_text' => 'Mo ta anh', 'title' => 'Tieu de', 'caption' => 'Chu thich', '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT alt_text, title, caption FROM media WHERE id = ?', [$mediaId]);
        self::assertSame('Mo ta anh', $row['alt_text']);
        self::assertSame('Tieu de', $row['title']);
        self::assertSame('Chu thich', $row['caption']);
    }

    public function testUpdateCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $mediaInB = $this->seedMedia($siteB, "{$siteB}/x.png", 100);
        $this->actingAs($siteA, $this->seedUser(), ['media.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/media/{$mediaInB}",
            'example.com',
            [],
            ['alt_text' => 'Hacked', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpdateMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $mediaId = $this->seedMedia($siteId, "{$siteId}/x.png", 100);
        $this->actingAs($siteId, $this->seedUser(), []);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/media/{$mediaId}",
            'example.com',
            [],
            ['alt_text' => 'x', '_token' => $this->csrfToken()]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Delete ----

    public function testDeleteSuccessRemovesRowFileAndDecreasesStorageUsed(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['media.upload', 'media.delete']);

        $pngBytes = $this->realPngBytes();
        $file = $this->fakeUploadedFile('to-delete.png', 'image/png', $pngBytes);
        $uploadResponse = $this->router->dispatch(new Request(
            'POST',
            '/media',
            'example.com',
            [],
            ['_token' => $this->csrfToken()],
            [],
            [],
            ['file' => $file]
        ));
        $uploaded = \json_decode($uploadResponse->getBody(), true)['data'];
        $fullPath = $this->storageDir . '/' . $uploaded['path'];

        self::assertFileExists($fullPath);
        self::assertSame(\strlen($pngBytes), $this->storageUsedBytes($siteId));

        $response = $this->router->dispatch(new Request('DELETE', "/media/{$uploaded['id']}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(200, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM media WHERE id = ?', [$uploaded['id']]);
        self::assertNull($row);
        self::assertFileDoesNotExist($fullPath);
        self::assertSame(0, $this->storageUsedBytes($siteId));
    }

    public function testDeleteCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $mediaInB = $this->seedMedia($siteB, "{$siteB}/x.png", 100);
        $this->actingAs($siteA, $this->seedUser(), ['media.delete']);

        $response = $this->router->dispatch(new Request('DELETE', "/media/{$mediaInB}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(404, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaInB]);
        self::assertNotNull($row);
    }

    public function testDeleteMissingPermissionReturns403(): void
    {
        $siteId = $this->seedSite();
        $mediaId = $this->seedMedia($siteId, "{$siteId}/x.png", 100);
        $this->actingAs($siteId, $this->seedUser(), []);

        $response = $this->router->dispatch(new Request('DELETE', "/media/{$mediaId}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDeleteDoesNotThrowWhenFileAlreadyMissingFromDisk(): void
    {
        $siteId = $this->seedSite();
        $mediaId = $this->seedMedia($siteId, "{$siteId}/already-gone.png", 100);
        $this->actingAs($siteId, $this->seedUser(), ['media.delete']);

        $response = $this->router->dispatch(new Request('DELETE', "/media/{$mediaId}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(200, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaId]);
        self::assertNull($row);
    }
}
