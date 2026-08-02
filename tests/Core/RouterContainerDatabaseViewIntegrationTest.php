<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Container;
use Core\Database;
use Core\Http\Request;
use Core\Router;
use Core\View;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Http\IntegrationController;

/**
 * Regression Test: Router (CMS-006) phai ghep dung voi Container (CMS-003), Database (CMS-004),
 * View (CMS-005) ma KHONG can sua bat ky file nao trong 3 component do.
 */
final class RouterContainerDatabaseViewIntegrationTest extends TestCase
{
    public function testRouterDispatchesToControllerUsingDatabaseAndView(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $database = new Database($config);
        $view = new View(__DIR__ . '/../Fixtures/themes', 'active', 'default');

        $container = new Container();
        $container->instance(Database::class, $database);
        $container->instance(View::class, $view);

        $router = new Router($container);
        $router->get('/integration', [IntegrationController::class, 'index']);

        $response = $router->dispatch(new Request('GET', '/integration', 'example.com'));

        self::assertSame('Hello from active theme|1', $response->getBody());
    }
}
