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
 * Integration test cho Visual Page Builder (Phase 11) - luong Admin: tao/sua Page qua
 * editor_mode='block', validate 6 loai block MVP, cach ly tenant cho image block media_id.
 * Cung pattern AdminPageManagementUiTest.php.
 */
final class AdminPageBuilderTest extends TestCase
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

    private function seedMedia(int $tenantId, string $mimeType = 'image/png'): int
    {
        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, 'pic.png', $tenantId . '/pic.png', $mimeType, 100, 1]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedPageWithContent(int $tenantId, int $userId, string $slug, array $content): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, 'Page ' . $slug, $slug, \json_encode($content), 'published', $userId]
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

    // ---- Create ----

    public function testCreatePageWithBlockBuilderSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['page.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/pages/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $blocks = [
            ['type' => 'heading', 'text' => 'Xin chao', 'level' => 2],
            ['type' => 'paragraph', 'text' => 'Noi dung mau.'],
        ];

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            [
                'title' => 'Trang Block',
                'slug' => 'trang-block',
                'editor_mode' => 'block',
                'content_blocks_json' => \json_encode($blocks),
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT content FROM pages WHERE slug = ?', ['trang-block']);
        self::assertNotNull($row);
        $decoded = \json_decode($row['content'], true);
        self::assertSame($blocks, $decoded['blocks']);
    }

    public function testCreatePageWithInvalidBlockTypeSilentlyRedirects(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['page.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/pages/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $blocks = [['type' => 'not_a_real_type', 'text' => 'x']];

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            [
                'title' => 'Trang Loi',
                'slug' => 'trang-loi',
                'editor_mode' => 'block',
                'content_blocks_json' => \json_encode($blocks),
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM pages WHERE slug = ?', ['trang-loi']);
        self::assertNull($row);
    }

    public function testCreatePageWithMalformedJsonSilentlyRedirects(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $this->actingAs($siteId, $userId, ['page.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/pages/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            [
                'title' => 'Trang Loi Json',
                'slug' => 'trang-loi-json',
                'editor_mode' => 'block',
                'content_blocks_json' => '{not-valid-json',
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM pages WHERE slug = ?', ['trang-loi-json']);
        self::assertNull($row);
    }

    public function testCreatePageImageBlockWithValidMediaSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $mediaId = $this->seedMedia($siteId);
        $this->actingAs($siteId, $userId, ['page.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/pages/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $blocks = [['type' => 'image', 'media_id' => $mediaId, 'alt' => 'Anh mau']];

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            [
                'title' => 'Trang Anh',
                'slug' => 'trang-anh',
                'editor_mode' => 'block',
                'content_blocks_json' => \json_encode($blocks),
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT content FROM pages WHERE slug = ?', ['trang-anh']);
        self::assertNotNull($row);
        $decoded = \json_decode($row['content'], true);
        self::assertSame($mediaId, $decoded['blocks'][0]['media_id']);
    }

    public function testCreatePageImageBlockWithCrossTenantMediaRejected(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser();
        $mediaInB = $this->seedMedia($siteB);
        $this->actingAs($siteA, $userId, ['page.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/pages/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $blocks = [['type' => 'image', 'media_id' => $mediaInB, 'alt' => 'x']];

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            [
                'title' => 'Trang Anh Sai Tenant',
                'slug' => 'trang-anh-sai-tenant',
                'editor_mode' => 'block',
                'content_blocks_json' => \json_encode($blocks),
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM pages WHERE slug = ?', ['trang-anh-sai-tenant']);
        self::assertNull($row);
    }

    // ---- Show Edit: mode detection ----

    public function testEditPageShowsBlockModeWhenExistingContentHasBlocks(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPageWithContent($siteId, $userId, 'da-co-block', [
            'blocks' => [['type' => 'heading', 'text' => 'Tieu de', 'level' => 2]],
        ]);
        $this->actingAs($siteId, $userId, ['page.update']);

        $response = $this->router->dispatch(new Request('GET', "/admin/pages/{$pageId}/edit", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('name="editor_mode" id="editor-mode-input" value="block"', $response->getBody());
    }

    public function testEditPageShowsQuillModeWhenExistingContentHasHtml(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPageWithContent($siteId, $userId, 'da-co-html', ['html' => '<p>Xin chao</p>']);
        $this->actingAs($siteId, $userId, ['page.update']);

        $response = $this->router->dispatch(new Request('GET', "/admin/pages/{$pageId}/edit", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('name="editor_mode" id="editor-mode-input" value="quill"', $response->getBody());
    }

    // ---- Update ----

    public function testUpdatePageWithBlockBuilderSuccess(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPageWithContent($siteId, $userId, 'can-sua', ['html' => '<p>Cu</p>']);
        $this->actingAs($siteId, $userId, ['page.update']);

        $editPage = $this->router->dispatch(new Request('GET', "/admin/pages/{$pageId}/edit", 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $blocks = [['type' => 'paragraph', 'text' => 'Noi dung moi qua Block Builder']];

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/pages/{$pageId}",
            'example.com',
            [],
            [
                'title' => 'Da Sua',
                'editor_mode' => 'block',
                'content_blocks_json' => \json_encode($blocks),
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT content FROM pages WHERE id = ?', [$pageId]);
        $decoded = \json_decode($row['content'], true);
        self::assertSame($blocks, $decoded['blocks']);
    }

    public function testUpdatePageWithInvalidBlockTypeSilentlyRedirectsWithoutChangingData(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser();
        $pageId = $this->seedPageWithContent($siteId, $userId, 'khong-doi', ['html' => '<p>Giu nguyen</p>']);
        $this->actingAs($siteId, $userId, ['page.update']);

        $editPage = $this->router->dispatch(new Request('GET', "/admin/pages/{$pageId}/edit", 'example.com'));
        $token = $this->extractCsrfToken($editPage->getBody());

        $blocks = [['type' => 'invalid_type']];

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/pages/{$pageId}",
            'example.com',
            [],
            [
                'editor_mode' => 'block',
                'content_blocks_json' => \json_encode($blocks),
                '_token' => $token,
            ]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT content FROM pages WHERE id = ?', [$pageId]);
        $decoded = \json_decode($row['content'], true);
        self::assertSame('<p>Giu nguyen</p>', $decoded['html']);
    }
}
