<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Database;
use Core\Http\Request;
use Core\Security\AuditLogger;
use Core\Session;
use Core\TenantManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit test cho Phase 16 (Security & Audit Log System, CMS-053) - Core\Security\AuditLogger.
 * Goi truc tiep (khong Router) voi Database SQLite in-memory that + Request dung tay.
 */
final class AuditLoggerTest extends TestCase
{
    private Database $database;
    private Session $session;
    private TenantManager $tenantManager;
    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->database = new Database($config);
        $this->session = new Session($config);
        $this->tenantManager = new TenantManager();
        $this->auditLogger = new AuditLogger($this->database, $this->session, $this->tenantManager);

        $this->migrate();
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
        $this->database->statement('CREATE TABLE audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NULL,
            user_id BIGINT NULL,
            event VARCHAR(100) NOT NULL,
            auditable_type VARCHAR(20) NULL,
            auditable_id BIGINT NULL,
            old_values TEXT NULL,
            new_values TEXT NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }

    private function request(): Request
    {
        return new Request(
            'POST',
            '/admin/pages',
            'example.com',
            [],
            [],
            ['USER-AGENT' => 'PHPUnit-Agent/1.0'],
            [],
            [],
            [],
            ['REMOTE_ADDR' => '203.0.113.9']
        );
    }

    public function testLogInsertsRowWithCorrectEventAndTenant(): void
    {
        $this->tenantManager->setCurrent(1);

        $this->auditLogger->log($this->request(), 'page.created', 'page', 42);

        $row = $this->database->selectOne('SELECT * FROM audit_logs WHERE event = ?', ['page.created']);
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['tenant_id']);
        self::assertSame('page', $row['auditable_type']);
        self::assertSame(42, (int) $row['auditable_id']);
    }

    public function testLogCapturesIpAndUserAgentFromRequest(): void
    {
        $this->tenantManager->setCurrent(1);

        $this->auditLogger->log($this->request(), 'page.created');

        $row = $this->database->selectOne('SELECT ip_address, user_agent FROM audit_logs WHERE event = ?', ['page.created']);
        self::assertSame('203.0.113.9', $row['ip_address']);
        self::assertSame('PHPUnit-Agent/1.0', $row['user_agent']);
    }

    public function testLogCapturesUserIdFromSessionWhenAuthenticated(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->session->start();
        $this->session->set('auth.user_id', 7);

        $this->auditLogger->log($this->request(), 'auth.login_success');

        $row = $this->database->selectOne('SELECT user_id FROM audit_logs WHERE event = ?', ['auth.login_success']);
        self::assertSame(7, (int) $row['user_id']);
    }

    public function testLogUserIdIsNullWhenSessionNotStarted(): void
    {
        $this->tenantManager->setCurrent(1);

        $this->auditLogger->log($this->request(), 'auth.login_failed');

        $row = $this->database->selectOne('SELECT user_id FROM audit_logs WHERE event = ?', ['auth.login_failed']);
        self::assertNull($row['user_id']);
    }

    public function testLogEncodesOldAndNewValuesAsJson(): void
    {
        $this->tenantManager->setCurrent(1);

        $this->auditLogger->log(
            $this->request(),
            'page.updated',
            'page',
            5,
            ['title' => 'Cu'],
            ['title' => 'Moi']
        );

        $row = $this->database->selectOne('SELECT old_values, new_values FROM audit_logs WHERE event = ?', ['page.updated']);
        self::assertSame(['title' => 'Cu'], \json_decode((string) $row['old_values'], true));
        self::assertSame(['title' => 'Moi'], \json_decode((string) $row['new_values'], true));
    }

    public function testLogAllowsNullOldAndNewValues(): void
    {
        $this->tenantManager->setCurrent(1);

        $this->auditLogger->log($this->request(), 'auth.logout');

        $row = $this->database->selectOne('SELECT old_values, new_values FROM audit_logs WHERE event = ?', ['auth.logout']);
        self::assertNull($row['old_values']);
        self::assertNull($row['new_values']);
    }

    public function testLogSilentlyFailsWhenTableMissing(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $database = new Database($config);
        $tenantManager = new TenantManager();
        $tenantManager->setCurrent(1);
        $session = new Session($config);
        $logger = new AuditLogger($database, $session, $tenantManager);

        // Khong migrate() - bang audit_logs khong ton tai.
        $logger->log($this->request(), 'page.created');

        self::assertTrue(true, 'log() khong duoc throw du bang khong ton tai.');
    }

    public function testLogsAreTaggedWithCurrentTenantAtCallTime(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->auditLogger->log($this->request(), 'page.created', 'page', 1);

        $this->tenantManager->setCurrent(2);
        $this->auditLogger->log($this->request(), 'page.created', 'page', 2);

        $tenant1Logs = $this->database->select('SELECT * FROM audit_logs WHERE tenant_id = ?', [1]);
        $tenant2Logs = $this->database->select('SELECT * FROM audit_logs WHERE tenant_id = ?', [2]);

        self::assertCount(1, $tenant1Logs);
        self::assertCount(1, $tenant2Logs);
        self::assertSame(1, (int) $tenant1Logs[0]['auditable_id']);
        self::assertSame(2, (int) $tenant2Logs[0]['auditable_id']);
    }
}
