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

/** Integration test cho Phase 24 (Email SMTP Settings, CMS-081) - GET /admin/email-settings + POST .../test. */
final class AdminEmailSettingsTest extends TestCase
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
        $this->container->singleton(View::class, static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default'));

        $this->mailerDriver = new ArrayMailerDriver();
        $this->container->instance(MailerDriver::class, $this->mailerDriver);
        $this->container->singleton(Mailer::class, static fn (Container $c): Mailer => new Mailer(
            $c->get(MailerDriver::class),
            $c->get(View::class),
            new Logger(\sys_get_temp_dir() . '/cms-test-email-settings-error.log')
        ));

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->database->statement('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL)');
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);

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

    public function testListRequiresSettingsManagePermission(): void
    {
        $this->actingAs(1, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/email-settings', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testListShowsActiveMailDriverConfig(): void
    {
        $this->actingAs(1, ['settings.manage']);

        $response = $this->router->dispatch(new Request('GET', '/admin/email-settings', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Cấu hình Email', $response->getBody());
    }

    public function testSendTestEmailRequiresSettingsManagePermission(): void
    {
        $this->actingAs(1, []);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/email-settings/test',
            'example.com',
            [],
            ['to' => 'a@example.com', '_token' => $this->csrfToken()]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testSendTestEmailDispatchesThroughMailer(): void
    {
        $this->actingAs(1, ['settings.manage']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/email-settings/test',
            'example.com',
            [],
            ['to' => 'nguoi-nhan@example.com', '_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());

        $sent = $this->mailerDriver->sent();
        self::assertCount(1, $sent);
        self::assertSame('nguoi-nhan@example.com', $sent[0]['to']);
    }

    public function testSendTestEmailRejectsInvalidAddress(): void
    {
        $this->actingAs(1, ['settings.manage']);

        $this->router->dispatch(new Request(
            'POST',
            '/admin/email-settings/test',
            'example.com',
            [],
            ['to' => 'khong-hop-le', '_token' => $this->csrfToken()]
        ));

        self::assertCount(0, $this->mailerDriver->sent());
    }
}
