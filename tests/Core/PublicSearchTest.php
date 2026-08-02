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
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Public Search (GET /search?q=) - modules/Public/SearchController. LIKE
 * tren title/content, scoped tenant hien tai, chi status=published, LIMIT 50. Dung fixture theme
 * rieng (cung pattern PublicPageRenderingTest - CMS-044 Decision 4), can view "pages.search" fixture.
 */
final class PublicSearchTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const FIXTURE_THEMES_PATH = __DIR__ . '/../Fixtures/themes';

    private Container $container;
    private Router $router;
    private Database $database;
    private TenantManager $tenantManager;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(
            View::class,
            static fn (): View => new View(self::FIXTURE_THEMES_PATH, 'test-theme', 'test-theme')
        );

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['auth', 'user', 'role', 'dashboard', 'page', 'settings', 'media', 'public']);
    }

    private function migrate(): void
    {
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
    }

    /** @param array<string, mixed> $overrides */
    private function seedPage(int $tenantId, array $overrides = []): int
    {
        $defaults = [
            'title' => 'About Us',
            'slug' => 'about-' . \uniqid('', true),
            'content' => \json_encode(['text' => 'hello world']),
            'status' => 'published',
            'deleted_at' => null,
        ];
        $row = [...$defaults, ...$overrides];

        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, created_by, deleted_at)
             VALUES (?, ?, ?, ?, ?, 1, ?)',
            [$tenantId, $row['title'], $row['slug'], $row['content'], $row['status'], $row['deleted_at']]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    // ---- Matching ----

    public function testSearchFindsPageByTitle(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['title' => 'Unique Kangaroo Title']);

        $response = $this->router->dispatch(new Request('GET', '/search', 'example.com', ['q' => 'Kangaroo']));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Unique Kangaroo Title', $response->getBody());
    }

    public function testSearchFindsPageByContent(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['title' => 'Normal Page', 'content' => \json_encode(['text' => 'contains platypus somewhere'])]);

        $response = $this->router->dispatch(new Request('GET', '/search', 'example.com', ['q' => 'platypus']));

        self::assertStringContainsString('Normal Page', $response->getBody());
    }

    public function testSearchWithNoMatchReturnsEmptyResults(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['title' => 'Unrelated Page']);

        $response = $this->router->dispatch(new Request('GET', '/search', 'example.com', ['q' => 'zzz-no-match-zzz']));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Unrelated Page', $response->getBody());
    }

    // ---- Scoping ----

    public function testSearchIsScopedToCurrentTenant(): void
    {
        $this->seedPage(2, ['title' => 'Tenant Two Exclusive Page']);
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['title' => 'Tenant One Page']);

        $response = $this->router->dispatch(new Request('GET', '/search', 'example.com', ['q' => 'Page']));

        self::assertStringContainsString('Tenant One Page', $response->getBody());
        self::assertStringNotContainsString('Tenant Two Exclusive Page', $response->getBody());
    }

    public function testSearchOnlyReturnsPublishedPages(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['title' => 'Draft Search Target', 'status' => 'draft']);
        $this->seedPage(1, ['title' => 'Published Search Target', 'status' => 'published']);

        $response = $this->router->dispatch(new Request('GET', '/search', 'example.com', ['q' => 'Search Target']));

        self::assertStringContainsString('Published Search Target', $response->getBody());
        self::assertStringNotContainsString('Draft Search Target', $response->getBody());
    }

    public function testSearchExcludesDeletedPages(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['title' => 'Deleted Search Target', 'deleted_at' => '2026-01-01 00:00:00']);

        $response = $this->router->dispatch(new Request('GET', '/search', 'example.com', ['q' => 'Deleted Search Target']));

        // Khong dung assertStringNotContainsString('Deleted Search Target', ...) truc tiep vi view
        // luon echo lai chinh query nguoi dung nhap ("<p>Query: Deleted Search Target</p>") bat ke
        // co ket qua hay khong - gay false positive neu assert tren chuoi tho. Assert dung tren
        // markup <p>{title}</p> ma chi vong lap $results moi sinh ra.
        self::assertStringNotContainsString('<p>Deleted Search Target</p>', $response->getBody());
    }

    // ---- Edge cases ----

    public function testSearchWithEmptyQueryReturnsEmptyResultsWithoutError(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['title' => 'Some Page']);

        $response = $this->router->dispatch(new Request('GET', '/search', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Some Page', $response->getBody());
    }

    public function testSearchXssPayloadInQueryIsEscaped(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['title' => 'Some Page']);

        $response = $this->router->dispatch(new Request(
            'GET',
            '/search',
            'example.com',
            ['q' => '<script>alert(1)</script>']
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->getBody());
        self::assertStringContainsString('&lt;script&gt;', $response->getBody());
    }

    public function testSearchWithLikeWildcardCharactersDoesNotError(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, ['title' => 'Some Page']);

        $response = $this->router->dispatch(new Request('GET', '/search', 'example.com', ['q' => '100%_off']));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testSearchLimitsResultsTo50(): void
    {
        $this->tenantManager->setCurrent(1);

        for ($i = 0; $i < 55; $i++) {
            $this->seedPage(1, ['title' => "Bulk Result Page {$i}"]);
        }

        $response = $this->router->dispatch(new Request('GET', '/search', 'example.com', ['q' => 'Bulk Result Page']));

        // Dung tien to markup "<p>Bulk Result Page" (khong phai chuoi tho "Bulk Result Page") de
        // khong dem nham dong "<p>Query: Bulk Result Page</p>" ma view luon echo lai query - dong
        // do khong bat dau bang "<p>Bulk" nen khong khop tien to nay.
        $matchCount = \substr_count($response->getBody(), '<p>Bulk Result Page');
        self::assertLessThanOrEqual(50, $matchCount);
    }
}
