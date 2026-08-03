<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use Core\Cache\CacheDriver;
use Core\Cache\FileCacheDriver;
use Core\Config;
use Core\Container;
use Core\Database;
use Core\Hook;
use Core\Http\Request;
use Core\Mail\Drivers\ArrayMailerDriver;
use Core\Mail\Mailer;
use Core\Mail\MailerDriver;
use Core\Logger;
use Core\PluginActivationService;
use Core\PluginManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Phase 20 (CMS-057) - POST /payment/webhook/{driver}, dung tien le
 * EcommerceCheckoutTest.php (PluginManager that + Hook "plugin.routes.register", khong ModuleManager).
 * Khong boc CsrfMiddleware - cac test o day tu no da chung minh dieu do (khong gui _token ma van
 * thanh cong qua duoc guard chu ky).
 */
final class PaymentWebhookControllerTest extends TestCase
{
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';
    private const REAL_PLUGINS_PATH = __DIR__ . '/../../plugins';

    private Container $container;
    private Router $router;
    private Database $database;
    private Session $session;
    private TenantManager $tenantManager;
    private PluginActivationService $pluginActivation;
    private string $cachePath;
    private string $logPath;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-payment-webhook-' . \uniqid('', true);
        $this->logPath = \sys_get_temp_dir() . '/cms-test-payment-webhook-' . \uniqid('', true) . '.log';

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(View::class, static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default'));
        $this->container->singleton(CacheDriver::class, fn (): FileCacheDriver => new FileCacheDriver($this->cachePath));
        $this->container->singleton(Cache::class, static fn (Container $c): Cache => new Cache($c->get(CacheDriver::class)));
        $this->container->singleton(Hook::class, static fn (): Hook => new Hook());
        $this->container->singleton(MailerDriver::class, static fn (): ArrayMailerDriver => new ArrayMailerDriver());
        $this->container->singleton(Mailer::class, fn (Container $c): Mailer => new Mailer($c->get(MailerDriver::class), $c->get(View::class), new Logger($this->logPath)));

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);
        $this->pluginActivation = $this->container->get(PluginActivationService::class);

        $this->migrate();

        $pluginManager = new PluginManager(self::REAL_PLUGINS_PATH);
        $pluginManager->boot($this->container->get(Hook::class), ['ecommerce']);
        $this->container->get(Hook::class)->do('plugin.routes.register', $this->router);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];

        if (\is_dir($this->cachePath)) {
            $this->removeDirectory($this->cachePath);
        }

        if (\is_file($this->logPath)) {
            @\unlink($this->logPath);
        }
    }

    private function removeDirectory(string $path): void
    {
        foreach (\glob($path . '/*') ?: [] as $file) {
            \is_dir($file) ? $this->removeDirectory($file) : @\unlink($file);
        }

        @\rmdir($path);
    }

    private function migrate(): void
    {
        $this->database->statement('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL)');
        $this->database->statement('CREATE TABLE site_plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, plugin_key VARCHAR(100) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 0, activated_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )');
        $this->database->statement("CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, order_number VARCHAR(40) NOT NULL,
            guest_name VARCHAR(255) NOT NULL, guest_email VARCHAR(255) NOT NULL, shipping_address TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending', total_amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(20) NOT NULL DEFAULT 'cod', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )");
        $this->database->statement('CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT, order_id BIGINT NOT NULL, product_id BIGINT NOT NULL,
            product_variant_id BIGINT NULL, product_name_snapshot VARCHAR(255) NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL, quantity INT NOT NULL, subtotal DECIMAL(12,2) NOT NULL
        )');
        $this->database->statement("CREATE TABLE payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, order_id BIGINT NOT NULL,
            driver VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', amount DECIMAL(12,2) NOT NULL,
            transaction_ref VARCHAR(100) NULL, raw_payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )");
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedOrder(int $tenantId, string $orderNumber = 'ORD-1'): int
    {
        $this->database->insert(
            "INSERT INTO orders (tenant_id, order_number, guest_name, guest_email, status, total_amount, payment_method)
             VALUES (?, ?, 'Nguyen Van A', 'a@example.com', 'pending', 100000, 'momo')",
            [$tenantId, $orderNumber]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function activateEcommerce(int $siteId): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->pluginActivation->activate($siteId, 'ecommerce');
    }

    /** @return array<string, mixed> */
    private function validMomoPayload(string $orderNumber): array
    {
        $data = [
            'partnerCode' => 'TEST_PARTNER',
            'orderId' => $orderNumber,
            'requestId' => 'REQ-1',
            'amount' => '100000',
            'orderInfo' => 'Thanh toan don hang ' . $orderNumber,
            'orderType' => 'momo_wallet',
            'transId' => 'TXN-123',
            'resultCode' => 0,
            'message' => 'Success',
            'payType' => 'qr',
            'responseTime' => '1234567890',
            'extraData' => '',
        ];

        $raw = "accessKey=test-access-key&amount={$data['amount']}&extraData={$data['extraData']}"
            . "&message={$data['message']}&orderId={$data['orderId']}&orderInfo={$data['orderInfo']}&orderType={$data['orderType']}"
            . "&partnerCode={$data['partnerCode']}&payType={$data['payType']}&requestId={$data['requestId']}"
            . "&responseTime={$data['responseTime']}&resultCode={$data['resultCode']}&transId={$data['transId']}";

        $data['signature'] = \hash_hmac('sha256', $raw, 'test-momo-secret');

        return $data;
    }

    public function testUnknownDriverReturns404(): void
    {
        $siteId = $this->seedSite();
        $this->activateEcommerce($siteId);

        $response = $this->router->dispatch(new Request('POST', '/payment/webhook/paypal', 'example.com', [], ['x' => 'y']));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testInvalidSignatureReturns403(): void
    {
        $siteId = $this->seedSite();
        $this->activateEcommerce($siteId);
        $this->seedOrder($siteId);

        $data = $this->validMomoPayload('ORD-1');
        $data['signature'] = 'wrong';

        $response = $this->router->dispatch(new Request('POST', '/payment/webhook/momo', 'example.com', [], $data));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([], $this->database->select('SELECT * FROM payments'));
    }

    public function testWebhookForUnknownOrderReturns404(): void
    {
        $siteId = $this->seedSite();
        $this->activateEcommerce($siteId);

        $response = $this->router->dispatch(new Request('POST', '/payment/webhook/momo', 'example.com', [], $this->validMomoPayload('KHONG-TON-TAI')));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testValidWebhookRecordsPaymentAndAdvancesOrderToProcessing(): void
    {
        $siteId = $this->seedSite();
        $this->activateEcommerce($siteId);
        $orderId = $this->seedOrder($siteId);

        $response = $this->router->dispatch(new Request('POST', '/payment/webhook/momo', 'example.com', [], $this->validMomoPayload('ORD-1')));

        self::assertSame(200, $response->getStatusCode());

        $payment = $this->database->selectOne('SELECT * FROM payments WHERE order_id = ?', [$orderId]);
        self::assertNotNull($payment);
        self::assertSame('completed', $payment['status']);
        self::assertSame('TXN-123', $payment['transaction_ref']);

        $order = $this->database->selectOne('SELECT status FROM orders WHERE id = ?', [$orderId]);
        self::assertSame('processing', $order['status']);
    }

    public function testDuplicateWebhookCallDoesNotDuplicatePaymentRow(): void
    {
        $siteId = $this->seedSite();
        $this->activateEcommerce($siteId);
        $this->seedOrder($siteId);
        $payload = $this->validMomoPayload('ORD-1');

        $first = $this->router->dispatch(new Request('POST', '/payment/webhook/momo', 'example.com', [], $payload));
        $second = $this->router->dispatch(new Request('POST', '/payment/webhook/momo', 'example.com', [], $payload));

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());

        $payments = $this->database->select('SELECT * FROM payments');
        self::assertCount(1, $payments, 'Webhook goi lai voi cung transaction_ref khong duoc tao them dong moi.');
    }
}
