<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Container;
use Core\Http\Request;
use Core\ModuleManager;
use Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Regression: ModuleManager (CMS-010) phai rap dung qua Container (CMS-003) va Router (CMS-006) -
 * khong sua file nao cua 2 component do.
 */
final class ModuleManagerContainerIntegrationTest extends TestCase
{
    public function testModuleManagerBootsRoutesIntoRouterResolvedThroughContainer(): void
    {
        $container = new Container();
        $container->singleton(
            ModuleManager::class,
            static fn (): ModuleManager => new ModuleManager(__DIR__ . '/../Fixtures/Modules')
        );

        $router = new Router($container);
        $moduleManager = $container->get(ModuleManager::class);

        $loaded = $moduleManager->boot($router, ['alpha']);

        self::assertSame(['alpha'], $loaded);

        $response = $router->dispatch(new Request('GET', '/alpha', 'example.com'));

        self::assertSame('alpha', $response->getBody());
    }
}
