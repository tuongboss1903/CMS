<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Container;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Regression check: View (CMS-005) phai rap dung qua Container (CMS-003) ma khong can sua
 * Container - View co tham so constructor kieu scalar (string) nen bat buoc phai bind() bang
 * Closure (dung nhu thiet ke Container da mo ta cho truong hop Config).
 */
final class ViewContainerIntegrationTest extends TestCase
{
    public function testViewCanBeResolvedThroughContainerAsSingleton(): void
    {
        $container = new Container();

        $container->singleton(View::class, fn (): View => new View(
            __DIR__ . '/../Fixtures/themes',
            'active',
            'default'
        ));

        $view = $container->get(View::class);

        self::assertInstanceOf(View::class, $view);
        self::assertSame('Hello from active theme', trim($view->render('greeting')));
        self::assertSame($view, $container->get(View::class));
    }
}
