<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Logger;
use Core\Mail\Drivers\ArrayMailerDriver;
use Core\Mail\Mailer;
use Core\Mail\MailerDriver;
use Core\ModuleManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Phase 14 (Comment/Review System, CMS-051) - luong Public gui Comment
 * (Modules\Public\CommentSubmitController). Dung fixture theme test-theme (khong render form
 * comment - fixture chi phuc vu render Page co ban). Lay CSRF token TRUC TIEP tu Core\Csrf::token()
 * qua Container (Session that, khong mock) thay vi parse tu HTML - token khong phu thuoc theme
 * nao co render form hay khong.
 *
 * Phase 15 (CMS-052): CommentSubmitController gio can NotificationService (constructor moi) ->
 * NotificationService can Core\Mail\Mailer (interface MailerDriver, KHONG the auto-wire tu Container
 * neu khong dang ky - khac AnalyticsService/LocaleDetectionMiddleware truoc do la class cu the).
 * Dang ky ArrayMailerDriver (test double, khong gui that) + bang "notifications" moi trong migrate().
 * fixture theme "test-theme" khong co themes/emails/* nen View::render() se That Bai khi Mailer thu
 * render template - Mailer::send() tu than da bat Throwable noi bo (silent-fail), KHONG anh huong
 * ket qua cac test hien co (chi kiem tra bang comments, khong kiem tra notifications/email).
 */
final class CommentSubmissionTest extends TestCase
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
        $this->container->singleton(
            View::class,
            static fn (): View => new View(__DIR__ . '/../Fixtures/themes', 'test-theme', 'test-theme')
        );
        $this->container->singleton(MailerDriver::class, static fn (): ArrayMailerDriver => new ArrayMailerDriver());
        $this->container->singleton(Mailer::class, static fn (Container $c): Mailer => new Mailer(
            $c->get(MailerDriver::class),
            $c->get(View::class),
            new Logger(\sys_get_temp_dir() . '/cms-test-mail-error.log')
        ));

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['auth', 'user', 'role', 'dashboard', 'page', 'settings', 'media', 'public']);
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
        $this->database->statement('CREATE TABLE comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT NOT NULL,
            guest_name VARCHAR(150) NOT NULL,
            guest_email VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            ip_hash VARCHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )');
        $this->database->statement('CREATE TABLE menus (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            name VARCHAR(150) NOT NULL,
            location_key VARCHAR(50) NOT NULL
        )');
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
        $this->database->statement('CREATE TABLE seo_meta (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT NOT NULL,
            title VARCHAR(255) NULL,
            description VARCHAR(500) NULL,
            canonical VARCHAR(500) NULL,
            og_image_id BIGINT NULL,
            og_title VARCHAR(255) NULL,
            og_description VARCHAR(500) NULL,
            is_index BOOLEAN NOT NULL DEFAULT 1,
            is_follow BOOLEAN NOT NULL DEFAULT 1,
            schema_type VARCHAR(50) NULL,
            schema_data TEXT NULL
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
        $this->database->statement('CREATE TABLE notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            type VARCHAR(50) NOT NULL,
            notifiable_type VARCHAR(20) NOT NULL,
            notifiable_id BIGINT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body VARCHAR(500) NOT NULL,
            read_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    }

    private function seedPage(int $tenantId, string $slug, string $status = 'published'): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, 'Page ' . $slug, $slug, \json_encode(['text' => 'Noi dung']), $status, 1]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function csrfToken(): string
    {
        return $this->container->get(Csrf::class)->token();
    }

    public function testSubmitValidCommentCreatesRecordWithPendingStatus(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'bai-viet');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/bai-viet/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Bai viet hay qua', '_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT * FROM comments WHERE entity_type = ? AND guest_name = ?', ['page', 'An']);
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);
        self::assertSame('an@example.com', $row['guest_email']);
    }

    public function testSubmitCommentWithMissingFieldsFailsValidation(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'bai-viet-2');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/bai-viet-2/comments',
            'example.com',
            [],
            ['guest_name' => '', 'guest_email' => '', 'body' => '', '_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(0, (int) $this->database->selectOne('SELECT COUNT(*) as count FROM comments')['count']);
    }

    public function testSubmitCommentWithInvalidEmailFailsValidation(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'bai-viet-3');

        $this->router->dispatch(new Request(
            'POST',
            '/bai-viet-3/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'khong-phai-email', 'body' => 'Noi dung', '_token' => $this->csrfToken()]
        ));

        self::assertSame(0, (int) $this->database->selectOne('SELECT COUNT(*) as count FROM comments')['count']);
    }

    public function testSubmitCommentWithInvalidCsrfTokenReturns419(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'bai-viet-4');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/bai-viet-4/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Noi dung', '_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }

    public function testSubmitCommentToNonexistentPageReturns404(): void
    {
        $this->tenantManager->setCurrent(1);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/khong-ton-tai/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Noi dung', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testSubmitCommentToUnpublishedPageReturns404(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'ban-nhap', 'draft');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/ban-nhap/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Noi dung', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRateLimitBlocksSixthSubmissionWithinWindow(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'bai-viet-5');
        $token = $this->csrfToken();

        for ($i = 1; $i <= 5; $i++) {
            $this->router->dispatch(new Request(
                'POST',
                '/bai-viet-5/comments',
                'example.com',
                [],
                ['guest_name' => "Nguoi {$i}", 'guest_email' => "user{$i}@example.com", 'body' => 'Noi dung', '_token' => $token]
            ));
        }

        self::assertSame(5, (int) $this->database->selectOne('SELECT COUNT(*) as count FROM comments')['count']);

        $this->router->dispatch(new Request(
            'POST',
            '/bai-viet-5/comments',
            'example.com',
            [],
            ['guest_name' => 'Nguoi 6', 'guest_email' => 'user6@example.com', 'body' => 'Noi dung', '_token' => $token]
        ));

        self::assertSame(5, (int) $this->database->selectOne('SELECT COUNT(*) as count FROM comments')['count']);
    }

    public function testIpAddressIsHashedNotStoredRaw(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'bai-viet-6');
        $rawIp = '198.51.100.7';

        $this->router->dispatch(new Request(
            'POST',
            '/bai-viet-6/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Noi dung', '_token' => $this->csrfToken()],
            [],
            [],
            [],
            [],
            ['REMOTE_ADDR' => $rawIp]
        ));

        $row = $this->database->selectOne('SELECT ip_hash FROM comments WHERE guest_name = ?', ['An']);
        self::assertNotNull($row);
        self::assertNotSame($rawIp, $row['ip_hash']);
        self::assertSame(64, \strlen((string) $row['ip_hash']));
    }

    public function testCommentTenantIdMatchesCurrentTenant(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->seedPage(1, 'bai-viet-7');

        $this->router->dispatch(new Request(
            'POST',
            '/bai-viet-7/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Noi dung', '_token' => $this->csrfToken()]
        ));

        $row = $this->database->selectOne('SELECT tenant_id FROM comments WHERE guest_name = ?', ['An']);
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['tenant_id']);
    }
}
