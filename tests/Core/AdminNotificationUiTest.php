<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Container;
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
 * Integration test cho Notification UI (Buoc 5, CMS-066) - Modules\Admin\Notification*Controller +
 * badge $unread_notifications_count trong core/Application.php::registerCoreServices() View
 * factory. Cung pattern AdminUserManagementUiTest (ModuleManager tro modules/ that). Test badge
 * dung lai chinh xac logic closure that (khong tu viet lai) - cung tien le
 * ApplicationPluginActivationIntegrationTest da dung cho admin.menu.items.
 */
final class AdminNotificationUiTest extends TestCase
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

        // Dung dung logic that cua Application::registerCoreServices() View factory cho phan
        // unread_notifications_count - khong tu viet lai, cung tien le ApplicationPluginActivationIntegrationTest.
        $this->container->singleton(View::class, function (Container $c): View {
            $session = $c->get(Session::class);
            $tenantManager = $c->get(TenantManager::class);
            $userId = $session->isStarted() ? $session->get('auth.user_id') : null;
            $unreadCount = 0;

            if ($userId !== null && $tenantManager->check()) {
                try {
                    $row = $c->get(Database::class)->selectOne(
                        'SELECT COUNT(*) as c FROM notifications WHERE tenant_id = ? AND user_id = ? AND read_at IS NULL',
                        [$tenantManager->id(), $userId]
                    );
                    $unreadCount = (int) ($row['c'] ?? 0);
                } catch (\Throwable) {
                }
            }

            return new View(self::REAL_THEMES_PATH, 'default', 'default', ['unread_notifications_count' => $unreadCount]);
        });
        $this->container->singleton(MailerDriver::class, static fn (): ArrayMailerDriver => new ArrayMailerDriver());
        $this->container->singleton(Mailer::class, static fn (Container $c): Mailer => new Mailer(
            $c->get(MailerDriver::class),
            $c->get(View::class),
            new Logger(\sys_get_temp_dir() . '/cms-test-admin-notification-mail.log')
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
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedUser(string $email): int
    {
        $this->database->insert(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)',
            ['User', $email, \password_hash('x', PASSWORD_DEFAULT)]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedNotification(int $siteId, int $userId, string $title, bool $read = false): int
    {
        $this->database->insert(
            'INSERT INTO notifications (tenant_id, user_id, type, notifiable_type, notifiable_id, title, body, read_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$siteId, $userId, 'comment.new', 'comment', 1, $title, 'Noi dung', $read ? \date('Y-m-d H:i:s') : null]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function actingAs(int $siteId, int $userId): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', $userId);
        $this->session->set('auth.user', ['id' => $userId, 'name' => 'User']);
        $this->session->set('auth.permissions', []);
    }

    private function extractCsrfToken(string $html): string
    {
        \preg_match('/name="_token" value="([^"]+)"/', $html, $matches);

        return $matches[1] ?? '';
    }

    // ---- List ----

    public function testListShowsOnlyOwnNotifications(): void
    {
        $siteId = $this->seedSite();
        $userA = $this->seedUser('a@example.com');
        $userB = $this->seedUser('b@example.com');
        $this->seedNotification($siteId, $userA, 'Thong bao cua A');
        $this->seedNotification($siteId, $userB, 'Thong bao cua B');
        $this->actingAs($siteId, $userA);

        $response = $this->router->dispatch(new Request('GET', '/admin/notifications', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Thong bao cua A', $response->getBody());
        self::assertStringNotContainsString('Thong bao cua B', $response->getBody());
    }

    public function testListShowsOnlyCurrentTenantNotifications(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $userId = $this->seedUser('a@example.com');
        $this->seedNotification($siteA, $userId, 'Thong bao Site A');
        $this->seedNotification($siteB, $userId, 'Thong bao Site B');
        $this->actingAs($siteA, $userId);

        $response = $this->router->dispatch(new Request('GET', '/admin/notifications', 'example.com'));

        self::assertStringContainsString('Thong bao Site A', $response->getBody());
        self::assertStringNotContainsString('Thong bao Site B', $response->getBody());
    }

    public function testListRedirectsToLoginWhenNotAuthenticated(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/admin/notifications', 'example.com'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/login', $response->getHeaders()['Location']);
    }

    // ---- Unread badge (sidebar) ----

    public function testSidebarShowsUnreadBadgeCount(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser('a@example.com');
        $this->seedNotification($siteId, $userId, 'Chua doc 1');
        $this->seedNotification($siteId, $userId, 'Chua doc 2');
        $this->seedNotification($siteId, $userId, 'Da doc roi', true);
        $this->actingAs($siteId, $userId);

        $response = $this->router->dispatch(new Request('GET', '/admin/notifications', 'example.com'));

        self::assertStringContainsString('badge-danger">2</span>', $response->getBody());
    }

    // ---- Mark read ----

    public function testMarkReadSetsReadAt(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser('a@example.com');
        $notificationId = $this->seedNotification($siteId, $userId, 'Chua doc');
        $this->actingAs($siteId, $userId);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/notifications', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/notifications/{$notificationId}/read",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT read_at FROM notifications WHERE id = ?', [$notificationId]);
        self::assertNotNull($row['read_at']);
    }

    public function testMarkReadOtherUsersNotificationReturns404(): void
    {
        $siteId = $this->seedSite();
        $userA = $this->seedUser('a@example.com');
        $userB = $this->seedUser('b@example.com');
        $notificationOfB = $this->seedNotification($siteId, $userB, 'Cua B');
        $this->actingAs($siteId, $userA);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/notifications', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/notifications/{$notificationOfB}/read",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(404, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT read_at FROM notifications WHERE id = ?', [$notificationOfB]);
        self::assertNull($row['read_at']);
    }

    // ---- Mark all read ----

    public function testMarkAllReadOnlyAffectsOwnUnread(): void
    {
        $siteId = $this->seedSite();
        $userA = $this->seedUser('a@example.com');
        $userB = $this->seedUser('b@example.com');
        $notifA1 = $this->seedNotification($siteId, $userA, 'A1');
        $notifA2 = $this->seedNotification($siteId, $userA, 'A2');
        $notifB = $this->seedNotification($siteId, $userB, 'B1');
        $this->actingAs($siteId, $userA);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/notifications', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/notifications/read-all',
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        foreach ([$notifA1, $notifA2] as $id) {
            $row = $this->database->selectOne('SELECT read_at FROM notifications WHERE id = ?', [$id]);
            self::assertNotNull($row['read_at']);
        }

        $rowB = $this->database->selectOne('SELECT read_at FROM notifications WHERE id = ?', [$notifB]);
        self::assertNull($rowB['read_at']);
    }

    // ---- CSRF ----

    public function testCsrfFailureReturns419(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedUser('a@example.com');
        $notificationId = $this->seedNotification($siteId, $userId, 'X');
        $this->actingAs($siteId, $userId);

        $this->router->dispatch(new Request('GET', '/admin/notifications', 'example.com'));

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/notifications/{$notificationId}/read",
            'example.com',
            [],
            ['_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }
}
