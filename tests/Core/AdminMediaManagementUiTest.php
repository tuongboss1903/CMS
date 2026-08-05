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
use Modules\Admin\MediaBulkDeleteController;
use Modules\Admin\MediaDeleteController;
use Modules\Admin\MediaFileController;
use Modules\Admin\MediaUploadController;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Admin Media Management UI (modules/Admin/Media*Controller) - cung pattern
 * AdminPageManagementUiTest, storagePath override qua Container::singleton() voi thu muc TEMP
 * rieng (khong ghi file that vao storage/app/media cua repo), cung pattern ModuleMediaIntegrationTest.
 */
final class AdminMediaManagementUiTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    /** PNG 1x1 that (magic byte hop le, base64) - can thiet vi MediaUploadController gio xac minh mime qua finfo_file(), khong con chap nhan byte gia tuy y. */
    private const REAL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /**
     * PNG 10x10 giai ma duoc that qua GD (khac REAL_PNG_BASE64 - fixture 1x1 o tren CHI du "that"
     * cho finfo_file() nhan dien magic byte, khong du du lieu IDAT that de GD tu giai ma - libpng
     * bao loi "Not enough image data" that khi imagecreatefrompng() thu doc). Chi dung rieng cho
     * test sinh thumbnail (can GD doc duoc that), khong doi REAL_PNG_BASE64 dung chung de tranh
     * anh huong cac test dang assert dung theo strlen() cua no (vd storage_used_bytes).
     */
    private const REAL_DECODABLE_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQYlWNMmXaCATdgwiM3gqUBKnQB1qndD6MAAAAASUVORK5CYII=';

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

        $this->storageDir = \sys_get_temp_dir() . '/cms_admin_media_test_' . \uniqid('', true);
        \mkdir($this->storageDir, 0755, true);
        $this->sourceDir = \sys_get_temp_dir() . '/cms_admin_media_source_' . \uniqid('', true);
        \mkdir($this->sourceDir, 0755, true);

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(
            View::class,
            static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default')
        );

        $storageDir = $this->storageDir;
        $this->container->singleton(
            MediaUploadController::class,
            static fn (Container $c): MediaUploadController => new MediaUploadController(
                $c->get(\Core\Authorization::class),
                $c->get(\Core\Auth::class),
                $c->get(Database::class),
                $c->get(TenantManager::class),
                $storageDir
            )
        );
        $this->container->singleton(
            MediaDeleteController::class,
            static fn (Container $c): MediaDeleteController => new MediaDeleteController(
                $c->get(\Core\Authorization::class),
                $c->get(Database::class),
                $c->get(TenantManager::class),
                $storageDir
            )
        );
        $this->container->singleton(
            MediaBulkDeleteController::class,
            static fn (Container $c): MediaBulkDeleteController => new MediaBulkDeleteController(
                $c->get(\Core\Authorization::class),
                $c->get(Database::class),
                $c->get(TenantManager::class),
                $storageDir
            )
        );
        $this->container->singleton(
            MediaFileController::class,
            static fn (Container $c): MediaFileController => new MediaFileController(
                $c->get(\Core\Authorization::class),
                $c->get(Database::class),
                $c->get(TenantManager::class),
                $storageDir
            )
        );
        $this->container->singleton(
            \Modules\Admin\MediaThumbnailController::class,
            static fn (Container $c): \Modules\Admin\MediaThumbnailController => new \Modules\Admin\MediaThumbnailController(
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
        $moduleManager->boot($this->router, ['auth', 'admin']);
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
            folder_id BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->statement('CREATE INDEX idx_media_tenant_id ON media (tenant_id)');
        $this->database->statement('CREATE TABLE media_folders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            parent_id BIGINT NULL,
            name VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->statement('CREATE TABLE media_variants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            media_id BIGINT NOT NULL,
            size_type VARCHAR(20) NOT NULL,
            path VARCHAR(500) NOT NULL,
            width INT NULL,
            height INT NULL
        )');
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

    private function seedMedia(int $siteId, string $path, int $size = 100, string $mimeType = 'image/png', string $fileName = 'existing.png'): int
    {
        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$siteId, $fileName, $path, $mimeType, $size, 1]
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

    public function testListFiltersByFileNameSearch(): void
    {
        $siteId = $this->seedSite();
        $this->seedMedia($siteId, "{$siteId}/a.png", 100, 'image/png', 'holiday-photo.png');
        $this->seedMedia($siteId, "{$siteId}/b.png", 100, 'image/png', 'invoice.pdf');
        $this->actingAs($siteId, $this->seedUser(), ['media.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com', ['q' => 'holiday']));

        self::assertStringContainsString('holiday-photo.png', $response->getBody());
        self::assertStringNotContainsString('invoice.pdf', $response->getBody());
    }

    public function testListShowsOnlyCurrentTenantMedia(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedMedia($siteA, "{$siteA}/a.png");
        $this->seedMedia($siteB, "{$siteB}/b.png");
        $this->actingAs($siteA, $this->seedUser(), ['media.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('existing.png', $response->getBody());
    }

    public function testListMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), []);

        $response = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaders()['Content-Type']);
    }

    // ---- Upload ----

    public function testUploadSuccessRedirectsAndIncreasesStorageUsed(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['media.view', 'media.upload']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $pngBytes = $this->realPngBytes();
        $file = $this->fakeUploadedFile('photo.png', 'image/png', $pngBytes);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media',
            'example.com',
            [],
            ['_token' => $token],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(\strlen($pngBytes), $this->storageUsedBytes($siteId));

        $row = $this->database->selectOne('SELECT tenant_id, path FROM media WHERE tenant_id = ?', [$siteId]);
        self::assertNotNull($row);
        self::assertFileExists($this->storageDir . '/' . $row['path']);
    }

    public function testUploadInvalidMimeIsRejectedWithoutCreatingRow(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['media.view', 'media.upload']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $file = $this->fakeUploadedFile('script.exe', 'application/x-msdownload', 'x');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media',
            'example.com',
            [],
            ['_token' => $token],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(302, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM media WHERE tenant_id = ?', [$siteId]);
        self::assertSame(0, (int) $count['c']);
    }

    // ---- File serving ----

    public function testFileControllerReturnsBytesWithContentType(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        \mkdir($this->storageDir . '/' . $siteId, 0755, true);
        \file_put_contents($this->storageDir . '/' . $siteId . '/pic.png', 'raw-bytes');
        $mediaId = $this->seedMedia($siteId, "{$siteId}/pic.png", 9, 'image/png');
        $this->actingAs($siteId, $userId, ['media.view']);

        $response = $this->router->dispatch(new Request('GET', "/admin/media/{$mediaId}/file", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaders()['Content-Type']);
        self::assertSame('raw-bytes', $response->getBody());
    }

    public function testFileControllerCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $mediaInB = $this->seedMedia($siteB, "{$siteB}/pic.png");
        $this->actingAs($siteA, $this->seedUser(), ['media.view']);

        $response = $this->router->dispatch(new Request('GET', "/admin/media/{$mediaInB}/file", 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Update ----

    public function testUpdateMetadataSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $mediaId = $this->seedMedia($siteId, "{$siteId}/x.png");
        $this->actingAs($siteId, $userId, ['media.view', 'media.update']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/media/{$mediaId}",
            'example.com',
            [],
            ['alt_text' => 'Mo ta anh', 'title' => 'Tieu de', 'caption' => 'Chu thich', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT alt_text, title, caption FROM media WHERE id = ?', [$mediaId]);
        self::assertSame('Mo ta anh', $row['alt_text']);
        self::assertSame('Tieu de', $row['title']);
        self::assertSame('Chu thich', $row['caption']);
    }

    public function testUpdateCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $mediaInB = $this->seedMedia($siteB, "{$siteB}/x.png");
        $this->actingAs($siteA, $userId, ['media.view', 'media.update']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/media/{$mediaInB}",
            'example.com',
            [],
            ['alt_text' => 'Hacked', '_token' => $token]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Delete ----

    public function testDeleteSuccessRemovesRowFileAndDecreasesStorageUsed(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        \mkdir($this->storageDir . '/' . $siteId, 0755, true);
        \file_put_contents($this->storageDir . '/' . $siteId . '/del.png', 'delete-me-bytes');
        $mediaId = $this->seedMedia($siteId, "{$siteId}/del.png", \strlen('delete-me-bytes'));
        $this->database->statement('UPDATE sites SET storage_used_bytes = ? WHERE id = ?', [\strlen('delete-me-bytes'), $siteId]);
        $this->actingAs($siteId, $userId, ['media.view', 'media.delete']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/media/{$mediaId}/delete",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaId]);
        self::assertNull($row);
        self::assertFileDoesNotExist($this->storageDir . '/' . $siteId . '/del.png');
        self::assertSame(0, $this->storageUsedBytes($siteId));
    }

    public function testDeleteCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $mediaInB = $this->seedMedia($siteB, "{$siteB}/x.png");
        $this->actingAs($siteA, $userId, ['media.view', 'media.delete']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/media/{$mediaInB}/delete",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(404, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaInB]);
        self::assertNotNull($row);
    }

    // ---- Bulk delete (Design Audit Phase 7) ----

    public function testBulkDeleteRemovesSelectedRowsFilesAndDecreasesStorage(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        \mkdir($this->storageDir . '/' . $siteId, 0755, true);
        \file_put_contents($this->storageDir . '/' . $siteId . '/a.png', 'aaaa');
        \file_put_contents($this->storageDir . '/' . $siteId . '/b.png', 'bbbbbb');
        $mediaA = $this->seedMedia($siteId, "{$siteId}/a.png", 4, 'image/png', 'a.png');
        $mediaB = $this->seedMedia($siteId, "{$siteId}/b.png", 6, 'image/png', 'b.png');
        $mediaC = $this->seedMedia($siteId, "{$siteId}/c.png", 8, 'image/png', 'c.png');
        $this->database->statement('UPDATE sites SET storage_used_bytes = ? WHERE id = ?', [18, $siteId]);
        $this->actingAs($siteId, $userId, ['media.view', 'media.delete']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media/bulk-delete',
            'example.com',
            [],
            ['_token' => $token, 'ids' => [(string) $mediaA, (string) $mediaB]]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertNull($this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaA]));
        self::assertNull($this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaB]));
        self::assertNotNull($this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaC]));
        self::assertFileDoesNotExist($this->storageDir . '/' . $siteId . '/a.png');
        self::assertFileDoesNotExist($this->storageDir . '/' . $siteId . '/b.png');
        self::assertSame(8, $this->storageUsedBytes($siteId));
    }

    public function testBulkDeleteIgnoresIdsFromOtherTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $mediaInA = $this->seedMedia($siteA, "{$siteA}/own.png");
        $mediaInB = $this->seedMedia($siteB, "{$siteB}/other.png");
        $this->actingAs($siteA, $userId, ['media.view', 'media.delete']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media/bulk-delete',
            'example.com',
            [],
            ['_token' => $token, 'ids' => [(string) $mediaInA, (string) $mediaInB]]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertNull($this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaInA]));
        self::assertNotNull($this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaInB]));
    }

    public function testBulkDeleteMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $mediaId = $this->seedMedia($siteId, "{$siteId}/a.png");
        $this->actingAs($siteId, $userId, ['media.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media/bulk-delete',
            'example.com',
            [],
            ['_token' => $token, 'ids' => [(string) $mediaId]]
        ));

        self::assertSame(403, $response->getStatusCode());
        self::assertNotNull($this->database->selectOne('SELECT id FROM media WHERE id = ?', [$mediaId]));
    }

    // ---- CSRF ----

    public function testCsrfFailureReturns419(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['media.view', 'media.upload']);

        $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));

        $file = $this->fakeUploadedFile('photo.png', 'image/png', 'x');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media',
            'example.com',
            [],
            ['_token' => 'invalid-token'],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(419, $response->getStatusCode());
    }

    // ---- Folder ----

    public function testCreateFolderThenListShowsItAsTab(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, $this->seedUser(), ['media.view', 'media.upload']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media/folders',
            'example.com',
            [],
            ['name' => 'Anh San Pham', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $folder = $this->database->selectOne('SELECT id, tenant_id, name FROM media_folders WHERE tenant_id = ?', [$siteId]);
        self::assertNotNull($folder);
        self::assertSame('Anh San Pham', $folder['name']);

        $page = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        self::assertStringContainsString('Anh San Pham', $page->getBody());
    }

    public function testListFiltersByFolderId(): void
    {
        $siteId = $this->seedSite();
        $this->database->insert('INSERT INTO media_folders (tenant_id, name) VALUES (?, ?)', [$siteId, 'Folder A']);
        $folderId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$siteId, 'in-folder.png', "{$siteId}/in-folder.png", 'image/png', 100, 1, $folderId]
        );
        $this->seedMedia($siteId, "{$siteId}/unfiled.png", 100, 'image/png', 'unfiled.png');
        $this->actingAs($siteId, $this->seedUser(), ['media.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com', ['folder_id' => (string) $folderId]));

        self::assertStringContainsString('in-folder.png', $response->getBody());
        self::assertStringNotContainsString('unfiled.png', $response->getBody());
    }

    public function testDeleteFolderUnsetsMediaFolderIdInsteadOfDeletingMedia(): void
    {
        $siteId = $this->seedSite();
        $this->database->insert('INSERT INTO media_folders (tenant_id, name) VALUES (?, ?)', [$siteId, 'Folder A']);
        $folderId = (int) $this->database->connection()->lastInsertId();
        $mediaId = $this->seedMedia($siteId, "{$siteId}/x.png");
        $this->database->statement('UPDATE media SET folder_id = ? WHERE id = ?', [$folderId, $mediaId]);
        $this->actingAs($siteId, $this->seedUser(), ['media.view', 'media.delete']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/media/folders/{$folderId}/delete",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertNull($this->database->selectOne('SELECT id FROM media_folders WHERE id = ?', [$folderId]));

        $row = $this->database->selectOne('SELECT folder_id FROM media WHERE id = ?', [$mediaId]);
        self::assertNotNull($row);
        self::assertNull($row['folder_id']);
    }

    public function testUpdateMovesMediaIntoFolder(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->database->insert('INSERT INTO media_folders (tenant_id, name) VALUES (?, ?)', [$siteId, 'Folder A']);
        $folderId = (int) $this->database->connection()->lastInsertId();
        $mediaId = $this->seedMedia($siteId, "{$siteId}/x.png");
        $this->actingAs($siteId, $userId, ['media.view', 'media.update']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/media/{$mediaId}",
            'example.com',
            [],
            ['folder_id' => (string) $folderId, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT folder_id FROM media WHERE id = ?', [$mediaId]);
        self::assertSame($folderId, (int) $row['folder_id']);
    }

    // ---- Thumbnail ----

    public function testUploadRealImageGeneratesThumbnailVariant(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['media.view', 'media.upload']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $file = $this->fakeUploadedFile('photo.png', 'image/png', (string) \base64_decode(self::REAL_DECODABLE_PNG_BASE64, true));

        $this->router->dispatch(new Request(
            'POST',
            '/admin/media',
            'example.com',
            [],
            ['_token' => $token],
            [],
            [],
            ['file' => $file]
        ));

        $media = $this->database->selectOne('SELECT id FROM media WHERE tenant_id = ?', [$siteId]);
        self::assertNotNull($media);

        $variant = $this->database->selectOne(
            "SELECT path, width, height FROM media_variants WHERE media_id = ? AND size_type = 'thumbnail'",
            [$media['id']]
        );
        self::assertNotNull($variant);
        self::assertFileExists($this->storageDir . '/' . $variant['path']);
    }

    public function testThumbnailControllerFallsBackToOriginalWhenNoVariant(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        \mkdir($this->storageDir . '/' . $siteId, 0755, true);
        \file_put_contents($this->storageDir . '/' . $siteId . '/pic.png', 'raw-bytes');
        $mediaId = $this->seedMedia($siteId, "{$siteId}/pic.png", 9, 'image/png');
        $this->actingAs($siteId, $userId, ['media.view']);

        $response = $this->router->dispatch(new Request('GET', "/admin/media/{$mediaId}/thumbnail", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('raw-bytes', $response->getBody());
    }
}
