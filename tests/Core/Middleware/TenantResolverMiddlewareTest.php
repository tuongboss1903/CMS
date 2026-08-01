<?php

declare(strict_types=1);

namespace Tests\Core\Middleware;

use Core\Config;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\TenantResolverMiddleware;
use Core\TenantManager;
use PHPUnit\Framework\TestCase;

final class TenantResolverMiddlewareTest extends TestCase
{
    private function freshDatabase(): Database
    {
        $config = new Config(__DIR__ . '/../../Fixtures/config');

        return new Database($config);
    }

    private function seedSite(Database $database, string $domain, ?string $themeActive = null): void
    {
        $database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            plan_id BIGINT NULL,
            theme_active VARCHAR(100) NULL,
            storage_used_bytes BIGINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )');
        $database->statement('CREATE TABLE site_domains (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id BIGINT NOT NULL,
            domain VARCHAR(255) NOT NULL,
            is_primary BOOLEAN NOT NULL DEFAULT 0
        )');

        $database->insert(
            'INSERT INTO sites (name, theme_active) VALUES (?, ?)',
            ['Site A', $themeActive]
        );
        $database->insert(
            'INSERT INTO site_domains (site_id, domain) VALUES (1, ?)',
            [$domain]
        );
    }

    public function testResolvesTenantSuccessfullyForKnownDomain(): void
    {
        $database = $this->freshDatabase();
        $this->seedSite($database, 'example.com');
        $tenantManager = new TenantManager();
        $middleware = new TenantResolverMiddleware($database, $tenantManager);

        $called = false;
        $response = $middleware->process(
            new Request('GET', '/', 'example.com'),
            function (Request $request) use (&$called): Response {
                $called = true;

                return Response::html('ok');
            }
        );

        self::assertTrue($called);
        self::assertSame('ok', $response->getBody());
    }

    public function testSetsCurrentTenantCorrectlyOnTenantManager(): void
    {
        $database = $this->freshDatabase();
        $this->seedSite($database, 'example.com', 'custom-theme');
        $tenantManager = new TenantManager();
        $middleware = new TenantResolverMiddleware($database, $tenantManager);

        $middleware->process(
            new Request('GET', '/', 'example.com'),
            fn (Request $request): Response => Response::html('ok')
        );

        self::assertTrue($tenantManager->check());
        self::assertSame(1, $tenantManager->id());
        self::assertSame('custom-theme', $tenantManager->current()['theme_active']);
    }

    public function testReturnsNotFoundJsonForUnknownDomain(): void
    {
        $database = $this->freshDatabase();
        $this->seedSite($database, 'known.example.com');
        $tenantManager = new TenantManager();
        $middleware = new TenantResolverMiddleware($database, $tenantManager);

        $called = false;
        $response = $middleware->process(
            new Request('GET', '/', 'unknown.example.com'),
            function (Request $request) use (&$called): Response {
                $called = true;

                return Response::html('should-not-reach-here');
            }
        );

        self::assertFalse($called);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'data' => null, 'message' => 'Not Found', 'errors' => []],
            \json_decode($response->getBody(), true)
        );
    }

    public function testDoesNotSetTenantWhenDomainUnknown(): void
    {
        $database = $this->freshDatabase();
        $this->seedSite($database, 'known.example.com');
        $tenantManager = new TenantManager();
        $middleware = new TenantResolverMiddleware($database, $tenantManager);

        $middleware->process(
            new Request('GET', '/', 'unknown.example.com'),
            fn (Request $request): Response => Response::html('ok')
        );

        self::assertFalse($tenantManager->check());
    }
}
