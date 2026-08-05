<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Database;
use Core\Hook;
use Core\Logger;
use Core\Mail\Drivers\ArrayMailerDriver;
use Core\Mail\Mailer;
use Core\TenantManager;
use Core\View;
use Modules\Admin\NotificationService;
use PHPUnit\Framework\TestCase;

/**
 * Phase 20 (CMS-057, Order Notification System). Nap thang plugins/Ecommerce/Hooks.php vao 1 Hook
 * instance that (khong qua PluginManager - test nay chi quan tam listener "order.*", khong can
 * "plugin.routes.register"/"admin.menu.items"). Mailer dung ArrayMailerDriver that (khong mock,
 * dung tien le MailerTest.php) - kiem tra email THAT DUOC RENDER qua View that (themes/default/
 * views/emails/order_*.php).
 */
final class OrderNotificationHookTest extends TestCase
{
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';
    private const HOOKS_FILE = __DIR__ . '/../../plugins/Ecommerce/Hooks.php';

    private Hook $hook;
    private ArrayMailerDriver $mailerDriver;
    private Mailer $mailer;
    private Database $database;
    private string $logPath;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->database = new Database($config);
        $this->logPath = \sys_get_temp_dir() . '/cms-test-order-notification-' . \uniqid('', true) . '.log';

        $this->mailerDriver = new ArrayMailerDriver();
        $view = new View(self::REAL_THEMES_PATH, 'default', 'default');
        $this->mailer = new Mailer($this->mailerDriver, $view, new Logger($this->logPath));

        $this->hook = new Hook();
        $hook = $this->hook;
        $hooksFile = self::HOOKS_FILE;
        (static function () use ($hook, $hooksFile): void {
            require $hooksFile;
        })();
    }

    protected function tearDown(): void
    {
        if (\is_file($this->logPath)) {
            @\unlink($this->logPath);
        }
    }

    private function migrateNotificationTables(): void
    {
        $this->database->statement('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL)');
        $this->database->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL)');
        $this->database->statement('CREATE TABLE user_site_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id BIGINT NOT NULL, site_id BIGINT NOT NULL, role_id BIGINT NOT NULL)');
        $this->database->statement('CREATE TABLE notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, user_id BIGINT NOT NULL,
            type VARCHAR(50) NOT NULL, notifiable_type VARCHAR(50) NOT NULL, notifiable_id BIGINT NOT NULL,
            title VARCHAR(255) NOT NULL, body TEXT NULL, read_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }

    /** @return array{id: int, order_number: string, guest_name: string, guest_email: string, total_amount: float} */
    private function sampleOrder(): array
    {
        return [
            'id' => 1,
            'order_number' => 'ORD-TEST-01',
            'guest_name' => 'Nguyen Van A',
            'guest_email' => 'a@example.com',
            'total_amount' => 100000.0,
        ];
    }

    public function testOrderCreatedSendsConfirmationEmailToGuest(): void
    {
        $this->hook->do('order.created', $this->sampleOrder(), $this->mailer, null);

        $sent = $this->mailerDriver->sent();
        self::assertCount(1, $sent);
        self::assertSame('a@example.com', $sent[0]['to']);
        self::assertStringContainsString('ORD-TEST-01', $sent[0]['subject']);
        self::assertStringContainsString('Nguyen Van A', $sent[0]['html']);
    }

    public function testOrderCreatedNotifiesAdminsViaNotificationService(): void
    {
        $this->migrateNotificationTables();
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $this->database->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Admin', 'admin@example.com']);
        $this->database->insert('INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (1, 1, 1)');

        $tenantManager = new TenantManager();
        $tenantManager->setCurrent(1, ['id' => 1]);
        $notificationService = new NotificationService($this->database, $this->mailer, $tenantManager);

        $this->hook->do('order.created', $this->sampleOrder(), $this->mailer, $notificationService);

        $notifications = $this->database->select('SELECT * FROM notifications');
        self::assertCount(1, $notifications);
        self::assertSame('order.created', $notifications[0]['type']);

        // 1 email cho khach (guest_email) + 1 email cho Admin (qua NotificationService::notifyAdmins()).
        self::assertCount(2, $this->mailerDriver->sent());
    }

    public function testOrderCreatedDoesNotThrowWhenMailerAndNotificationServiceNull(): void
    {
        $this->hook->do('order.created', $this->sampleOrder());

        self::assertSame([], $this->mailerDriver->sent());
    }

    public function testOrderPaymentCompletedSendsEmailToGuest(): void
    {
        $this->hook->do('order.payment_completed', $this->sampleOrder(), $this->mailer);

        $sent = $this->mailerDriver->sent();
        self::assertCount(1, $sent);
        self::assertSame('a@example.com', $sent[0]['to']);
        self::assertStringContainsString('Thanh toán thành công', $sent[0]['subject']);
    }

    public function testOrderShippedSendsEmailToGuest(): void
    {
        $this->hook->do('order.shipped', $this->sampleOrder(), $this->mailer);

        $sent = $this->mailerDriver->sent();
        self::assertCount(1, $sent);
        self::assertSame('a@example.com', $sent[0]['to']);
        self::assertStringContainsString('vận chuyển', $sent[0]['subject']);
    }
}
