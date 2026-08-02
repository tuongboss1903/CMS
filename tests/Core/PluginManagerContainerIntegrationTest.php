<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Container;
use Core\Hook;
use Core\PluginManager;
use PHPUnit\Framework\TestCase;

final class PluginManagerContainerIntegrationTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/../Fixtures/Plugins';

    public function testPluginManagerAndHookResolveThroughContainerAndBootTogether(): void
    {
        $container = new Container();
        $container->singleton(PluginManager::class, static fn (): PluginManager => new PluginManager(self::FIXTURE_PATH));
        $container->singleton(Hook::class, static fn (): Hook => new Hook());

        $pluginManager = $container->get(PluginManager::class);
        $hook = $container->get(Hook::class);

        $loaded = $pluginManager->boot($hook, ['pluginB', 'pluginA']);

        self::assertSame(['pluginA', 'pluginB'], $loaded);
        self::assertSame(['pluginA', 'pluginB'], $hook->apply('plugin.trace', []));
        self::assertCount(0, $pluginManager->getFailures());
    }

    public function testPluginManagerIsSingletonWithinSameContainer(): void
    {
        $container = new Container();
        $container->singleton(PluginManager::class, static fn (): PluginManager => new PluginManager(self::FIXTURE_PATH));

        self::assertSame($container->get(PluginManager::class), $container->get(PluginManager::class));
    }
}
