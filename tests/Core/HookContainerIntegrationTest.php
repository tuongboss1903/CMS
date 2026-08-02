<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Container;
use Core\Hook;
use PHPUnit\Framework\TestCase;

/**
 * Regression: Hook (CMS-009) phai rap dung qua Container (CMS-003) nhu 1 singleton dung CHUNG
 * giua nhieu "module"/"plugin" gia lap trong cung 1 request - khong sua Container.
 */
final class HookContainerIntegrationTest extends TestCase
{
    public function testHookIsResolvedAsSingletonAndSharedBetweenConsumers(): void
    {
        $container = new Container();
        $container->singleton(Hook::class, static fn (): Hook => new Hook());

        $moduleHook = $container->get(Hook::class);
        $pluginHook = $container->get(Hook::class);

        self::assertSame($moduleHook, $pluginHook);

        $received = null;
        $pluginHook->action('post.published', function (string $title) use (&$received): void {
            $received = $title;
        });

        $moduleHook->do('post.published', 'Hello World');

        self::assertSame('Hello World', $received);
    }

    public function testHookAutoWiresWithoutExplicitBindingSinceItHasNoConstructorDependencies(): void
    {
        $container = new Container();

        $hook = $container->get(Hook::class);

        self::assertInstanceOf(Hook::class, $hook);
    }
}
