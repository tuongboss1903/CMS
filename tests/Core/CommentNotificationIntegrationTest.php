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
 * Integration test cho Phase 15 (Notification & Email System, CMS-052) - noi lien luong Comment
 * (Phase 14) voi Notification/Email (Phase 15): submit Comment -> notification + email Admin;
 * Approve/Reject -> email khach. Boot ca 'admin' lan 'public' (cung pattern PageTranslationTest.php),
 * dung themes/default/ THAT (can render template emails/* that, khac cac file test khac dung
 * ArrayMailerDriver rieng le).
 */
final class CommentNotificationIntegrationTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    private Container $container;
    private Router $router;
    private Database $database;
    private Session $session;
    private TenantManager $tenantManager;
    private ArrayMailerDriver $mailerDriver;

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

        $this->mailerDriver = new ArrayMailerDriver();
        $this->container->singleton(MailerDriver::class, fn (): ArrayMailerDriver => $this->mailerDriver);
        $this->container->singleton(Mailer::class, static fn (Container $c): Mailer => new Mailer(
            $c->get(MailerDriver::class),
            $c->get(View::class),
            new Logger(\sys_get_temp_dir() . '/cms-test-comment-notification.log')
        ));

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['auth', 'user', 'role', 'dashboard', 'page', 'settings', 'media', 'admin', 'public']);
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
        $this->database->statement('CREATE TABLE user_site_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id BIGINT NOT NULL,
            site_id BIGINT NOT NULL,
            role_id BIGINT NOT NULL
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

    private function seedAdmin(int $siteId, string $email = 'admin@example.com'): int
    {
        $this->database->insert(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)',
            ['Admin', $email, \password_hash('x', PASSWORD_DEFAULT)]
        );
        $userId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
            [$userId, $siteId, 1]
        );

        return $userId;
    }

    private function seedPage(int $tenantId, string $slug = 'bai-viet'): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, content, status, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, 'Bai viet mau', $slug, \json_encode(['text' => 'Noi dung']), 'published', 1]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function csrfToken(): string
    {
        return $this->container->get(Csrf::class)->token();
    }

    /** @param list<string> $permissions */
    private function actingAsAdmin(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', 1);
        $this->session->set('auth.permissions', $permissions);
    }

    public function testSubmittingCommentCreatesNotificationForAdmin(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin@example.com');
        $this->seedPage($siteId, 'bai-viet');
        $this->tenantManager->setCurrent($siteId);

        $this->router->dispatch(new Request(
            'POST',
            '/bai-viet/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Binh luan moi', '_token' => $this->csrfToken()]
        ));

        $rows = $this->database->select('SELECT * FROM notifications WHERE tenant_id = ?', [$siteId]);
        self::assertCount(1, $rows);
        self::assertSame('comment.new', $rows[0]['type']);
        self::assertNull($rows[0]['read_at']);
    }

    public function testSubmittingCommentSendsEmailToAdmin(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin@example.com');
        $this->seedPage($siteId, 'bai-viet-2');
        $this->tenantManager->setCurrent($siteId);

        $this->router->dispatch(new Request(
            'POST',
            '/bai-viet-2/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Binh luan moi', '_token' => $this->csrfToken()]
        ));

        $sent = $this->mailerDriver->sent();
        self::assertCount(1, $sent);
        self::assertSame('admin@example.com', $sent[0]['to']);
        self::assertStringContainsString('An', $sent[0]['html']);
        self::assertStringContainsString('Binh luan moi', $sent[0]['html']);
    }

    public function testMultipleAdminsAllReceiveNotificationAndEmail(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin1@example.com');
        $this->seedAdmin($siteId, 'admin2@example.com');
        $this->seedPage($siteId, 'bai-viet-3');
        $this->tenantManager->setCurrent($siteId);

        $this->router->dispatch(new Request(
            'POST',
            '/bai-viet-3/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Binh luan moi', '_token' => $this->csrfToken()]
        ));

        self::assertCount(2, $this->database->select('SELECT * FROM notifications WHERE tenant_id = ?', [$siteId]));
        self::assertCount(2, $this->mailerDriver->sent());
    }

    public function testApprovingCommentSendsEmailToGuest(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin@example.com');
        $pageId = $this->seedPage($siteId, 'bai-viet-4');
        $this->database->insert(
            'INSERT INTO comments (tenant_id, entity_type, entity_id, guest_name, guest_email, body, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$siteId, 'page', $pageId, 'An', 'an@example.com', 'Binh luan cho duyet', 'pending']
        );
        $commentId = (int) $this->database->connection()->lastInsertId();

        $this->actingAsAdmin($siteId, ['comment.moderate']);

        $this->router->dispatch(new Request(
            'POST',
            "/admin/comments/{$commentId}/approve",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        $sent = $this->mailerDriver->sent();
        self::assertCount(1, $sent);
        self::assertSame('an@example.com', $sent[0]['to']);
        self::assertStringContainsString('được duyệt', $sent[0]['html']);
    }

    public function testRejectingCommentSendsEmailToGuest(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin@example.com');
        $pageId = $this->seedPage($siteId, 'bai-viet-5');
        $this->database->insert(
            'INSERT INTO comments (tenant_id, entity_type, entity_id, guest_name, guest_email, body, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$siteId, 'page', $pageId, 'An', 'an@example.com', 'Binh luan cho duyet', 'pending']
        );
        $commentId = (int) $this->database->connection()->lastInsertId();

        $this->actingAsAdmin($siteId, ['comment.moderate']);

        $this->router->dispatch(new Request(
            'POST',
            "/admin/comments/{$commentId}/reject",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        $sent = $this->mailerDriver->sent();
        self::assertCount(1, $sent);
        self::assertSame('an@example.com', $sent[0]['to']);
        self::assertStringContainsString('không được duyệt', $sent[0]['html']);
    }

    public function testGuestEmailIsNotAmongAdminNotificationRecipients(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin@example.com');
        $this->seedPage($siteId, 'bai-viet-6');
        $this->tenantManager->setCurrent($siteId);

        $this->router->dispatch(new Request(
            'POST',
            '/bai-viet-6/comments',
            'example.com',
            [],
            ['guest_name' => 'An', 'guest_email' => 'an@example.com', 'body' => 'Binh luan moi', '_token' => $this->csrfToken()]
        ));

        $sent = $this->mailerDriver->sent();
        self::assertCount(1, $sent);
        self::assertNotSame('an@example.com', $sent[0]['to']);
        self::assertSame('admin@example.com', $sent[0]['to']);
    }
}
