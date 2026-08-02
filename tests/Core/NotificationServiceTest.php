<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Database;
use Core\Logger;
use Core\Mail\Drivers\ArrayMailerDriver;
use Core\Mail\Mailer;
use Core\TenantManager;
use Core\View;
use Modules\Admin\NotificationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit test cho Phase 15 (Notification & Email System, CMS-052) - Modules\Admin\NotificationService
 * (khong Router - goi truc tiep, dung Database SQLite in-memory that + ArrayMailerDriver test
 * double). Trong tam: dung du lieu duoc tao, read/unread, cach ly Multi-tenant tuyet doi, va
 * silent-fail khi bang notifications chua ton tai.
 */
final class NotificationServiceTest extends TestCase
{
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    private Database $database;
    private TenantManager $tenantManager;
    private ArrayMailerDriver $mailerDriver;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->database = new Database($config);
        $this->tenantManager = new TenantManager();
        $this->mailerDriver = new ArrayMailerDriver();

        $view = new View(self::REAL_THEMES_PATH, 'default', 'default');
        $mailer = new Mailer($this->mailerDriver, $view, new Logger(\sys_get_temp_dir() . '/cms-test-notification-mail.log'));

        $this->notificationService = new NotificationService($this->database, $mailer, $this->tenantManager);

        $this->migrate();
    }

    private function migrate(): void
    {
        $this->database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL
        )');
        $this->database->statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL
        )');
        $this->database->statement('CREATE TABLE user_site_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id BIGINT NOT NULL,
            site_id BIGINT NOT NULL,
            role_id BIGINT NOT NULL
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

    private function seedAdmin(int $siteId, string $email): int
    {
        $this->database->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Admin', $email]);
        $userId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
            [$userId, $siteId, 1]
        );

        return $userId;
    }

    private function notify(): void
    {
        $this->notificationService->notifyAdmins(
            'comment.new',
            'comment',
            1,
            'Binh luan moi tu An',
            'Noi dung binh luan',
            'Binh luan moi can duyet',
            'emails.comment_new',
            ['guest_name' => 'An', 'page_title' => 'Trang chu', 'body' => 'Noi dung binh luan', 'admin_url' => '/admin/comments']
        );
    }

    public function testNotifyAdminsCreatesNotificationRowForEachTenantUser(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin1@example.com');
        $this->seedAdmin($siteId, 'admin2@example.com');
        $this->tenantManager->setCurrent($siteId);

        $this->notify();

        $rows = $this->database->select('SELECT * FROM notifications WHERE tenant_id = ?', [$siteId]);
        self::assertCount(2, $rows);
    }

    public function testNotifyAdminsSendsEmailToEachAdmin(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin1@example.com');
        $this->seedAdmin($siteId, 'admin2@example.com');
        $this->tenantManager->setCurrent($siteId);

        $this->notify();

        $sent = $this->mailerDriver->sent();
        self::assertCount(2, $sent);
        self::assertSame('admin1@example.com', $sent[0]['to']);
        self::assertSame('admin2@example.com', $sent[1]['to']);
    }

    public function testNewNotificationHasNullReadAt(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin1@example.com');
        $this->tenantManager->setCurrent($siteId);

        $this->notify();

        $row = $this->database->selectOne('SELECT read_at FROM notifications WHERE tenant_id = ?', [$siteId]);
        self::assertNull($row['read_at']);
    }

    public function testMarkAsReadSetsReadAtTimestamp(): void
    {
        $siteId = $this->seedSite();
        $this->seedAdmin($siteId, 'admin1@example.com');
        $this->tenantManager->setCurrent($siteId);
        $this->notify();

        $notificationId = (int) $this->database->selectOne('SELECT id FROM notifications WHERE tenant_id = ?', [$siteId])['id'];

        $this->notificationService->markAsRead($notificationId);

        $row = $this->database->selectOne('SELECT read_at FROM notifications WHERE id = ?', [$notificationId]);
        self::assertNotNull($row['read_at']);
    }

    public function testUnreadCountReturnsCorrectNumberAndExcludesRead(): void
    {
        $siteId = $this->seedSite();
        $userId = $this->seedAdmin($siteId, 'admin1@example.com');
        $this->tenantManager->setCurrent($siteId);
        $this->notify();
        $this->notify();

        self::assertSame(2, $this->notificationService->unreadCount($userId));

        $notificationId = (int) $this->database->selectOne('SELECT id FROM notifications WHERE tenant_id = ? LIMIT 1', [$siteId])['id'];
        $this->notificationService->markAsRead($notificationId);

        self::assertSame(1, $this->notificationService->unreadCount($userId));
    }

    public function testNotificationsAreIsolatedPerTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedAdmin($siteA, 'admin-a@example.com');
        $adminB = $this->seedAdmin($siteB, 'admin-b@example.com');

        $this->tenantManager->setCurrent($siteA);
        $this->notify();

        $this->tenantManager->setCurrent($siteB);
        self::assertSame(0, $this->notificationService->unreadCount($adminB));

        $rowsForB = $this->database->select('SELECT * FROM notifications WHERE tenant_id = ?', [$siteB]);
        self::assertCount(0, $rowsForB);
    }

    public function testNotifyAdminsSilentlyFailsWhenUsersTableMissing(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $database = new Database($config);
        $database->statement('CREATE TABLE notifications (
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
        // Khong tao bang "users"/"user_site_roles" - mo phong fixture cu thieu schema.

        $tenantManager = new TenantManager();
        $tenantManager->setCurrent(1);
        $mailerDriver = new ArrayMailerDriver();
        $view = new View(self::REAL_THEMES_PATH, 'default', 'default');
        $mailer = new Mailer($mailerDriver, $view, new Logger(\sys_get_temp_dir() . '/cms-test-notification-mail-2.log'));
        $service = new NotificationService($database, $mailer, $tenantManager);

        $service->notifyAdmins('comment.new', 'comment', 1, 'title', 'body', 'subject', 'emails.comment_new', []);

        self::assertSame([], $mailerDriver->sent());
    }
}
