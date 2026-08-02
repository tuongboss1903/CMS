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
 * Integration test cho Phase 14 (Comment/Review System, CMS-051) - luong Admin duyet Comment
 * (List/Approve/Reject/Delete). Dung dung pattern actingAs() Session-based cua
 * AdminPageBuilderTest.php (khong can login form that). CSRF token lay TRUC TIEP qua
 * Core\Csrf::token() (Container) thay vi parse tu HTML GET - list.php chi render "_token" BEN
 * TRONG vong lap tung dong comment, nen khi danh sach rong (vd tenant khac chua co comment) se
 * khong co token nao de parse (da phat hien qua PHPUnit that).
 *
 * Phase 15 (CMS-052): CommentApproveController/CommentRejectController gio can Core\Mail\Mailer
 * (interface MailerDriver, khong the auto-wire) - dang ky ArrayMailerDriver (test double).
 */
final class AdminCommentModerationTest extends TestCase
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
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'draft\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        )');
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
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedPage(int $tenantId, string $slug = 'trang-a'): int
    {
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, status) VALUES (?, ?, ?, ?)',
            [$tenantId, 'Trang ' . $slug, $slug, 'published']
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedComment(int $tenantId, int $pageId, string $status = 'pending', string $guestName = 'NguoiGuiA'): int
    {
        $this->database->insert(
            'INSERT INTO comments (tenant_id, entity_type, entity_id, guest_name, guest_email, body, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tenantId, 'page', $pageId, $guestName, 'guest@example.com', 'Noi dung binh luan', $status]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', 1);
        $this->session->set('auth.permissions', $permissions);
    }

    private function csrfToken(): string
    {
        return $this->container->get(Csrf::class)->token();
    }

    // ---- List ----

    public function testListDefaultsToPendingStatus(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->seedComment($siteId, $pageId, 'pending', 'NguoiChoDuyet');
        $this->seedComment($siteId, $pageId, 'approved', 'NguoiDaDuyet');
        $this->actingAs($siteId, ['comment.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/comments', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('NguoiChoDuyet', $response->getBody());
        self::assertStringNotContainsString('NguoiDaDuyet', $response->getBody());
    }

    public function testListFiltersByApprovedStatus(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->seedComment($siteId, $pageId, 'pending', 'NguoiChoDuyet');
        $this->seedComment($siteId, $pageId, 'approved', 'NguoiDaDuyet');
        $this->actingAs($siteId, ['comment.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/comments', 'example.com', ['status' => 'approved']));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('NguoiDaDuyet', $response->getBody());
        self::assertStringNotContainsString('NguoiChoDuyet', $response->getBody());
    }

    public function testListFiltersByRejectedStatus(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $this->seedComment($siteId, $pageId, 'rejected', 'NguoiBiTuChoi');
        $this->actingAs($siteId, ['comment.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/comments', 'example.com', ['status' => 'rejected']));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('NguoiBiTuChoi', $response->getBody());
    }

    public function testListRequiresCommentViewPermission(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/comments', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Approve ----

    public function testApproveChangesStatusToApproved(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $commentId = $this->seedComment($siteId, $pageId, 'pending');
        $this->actingAs($siteId, ['comment.view', 'comment.moderate']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/comments/{$commentId}/approve",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT status, updated_at FROM comments WHERE id = ?', [$commentId]);
        self::assertSame('approved', $row['status']);
        self::assertNotNull($row['updated_at']);
    }

    public function testApproveRequiresCommentModeratePermission(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $commentId = $this->seedComment($siteId, $pageId, 'pending');
        $this->actingAs($siteId, ['comment.view']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/comments/{$commentId}/approve",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testApproveCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $pageA = $this->seedPage($siteA, 'trang-a');
        $commentA = $this->seedComment($siteA, $pageA, 'pending');

        $this->actingAs($siteB, ['comment.view', 'comment.moderate']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/comments/{$commentA}/approve",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Reject ----

    public function testRejectChangesStatusToRejected(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $commentId = $this->seedComment($siteId, $pageId, 'pending');
        $this->actingAs($siteId, ['comment.view', 'comment.moderate']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/comments/{$commentId}/reject",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('rejected', $this->database->selectOne('SELECT status FROM comments WHERE id = ?', [$commentId])['status']);
    }

    // ---- Delete ----

    public function testDeleteRemovesCommentPermanently(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $commentId = $this->seedComment($siteId, $pageId, 'approved');
        $this->actingAs($siteId, ['comment.view', 'comment.delete']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/comments/{$commentId}/delete",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertNull($this->database->selectOne('SELECT id FROM comments WHERE id = ?', [$commentId]));
    }

    public function testDeleteRequiresCommentDeletePermission(): void
    {
        $siteId = $this->seedSite();
        $pageId = $this->seedPage($siteId);
        $commentId = $this->seedComment($siteId, $pageId, 'approved');
        $this->actingAs($siteId, ['comment.view']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/comments/{$commentId}/delete",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(403, $response->getStatusCode());
    }
}
