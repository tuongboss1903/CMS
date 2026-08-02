<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Hook;
use Core\Plugin\CircularPluginDependencyException;
use Core\Plugin\PluginException;
use Core\Plugin\PluginNotFoundException;
use Core\PluginManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PluginManagerTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/../Fixtures/Plugins';
    private const INVALID_FIXTURE_PATH = __DIR__ . '/../Fixtures/PluginsInvalid';
    private const DUPLICATE_FIXTURE_PATH = __DIR__ . '/../Fixtures/PluginsDuplicate';

    public function testDiscoverFindsAllPluginsInDirectory(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);

        $descriptors = $manager->discover();

        self::assertArrayHasKey('pluginA', $descriptors);
        self::assertArrayHasKey('pluginB', $descriptors);
        self::assertArrayHasKey('broken', $descriptors);
        self::assertArrayHasKey('nohooks', $descriptors);
        self::assertArrayHasKey('circularA', $descriptors);
        self::assertArrayHasKey('circularB', $descriptors);
    }

    public function testDiscoverParsesManifestFieldsCorrectly(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);

        $descriptor = $manager->discover()['pluginB'];

        self::assertSame('pluginB', $descriptor->key);
        self::assertSame('Plugin B', $descriptor->name);
        self::assertSame('1.0.0', $descriptor->version);
        self::assertSame('Test Author', $descriptor->author);
        self::assertSame(['pluginA'], $descriptor->dependencies);
    }

    public function testDiscoverMemoizesAndOnlyParsesManifestsOnce(): void
    {
        $tempDir = \sys_get_temp_dir() . '/cms-plugin-memoize-' . \uniqid('', true);
        \mkdir($tempDir . '/pluginX', 0775, true);
        \file_put_contents(
            $tempDir . '/pluginX/plugin.json',
            '{"key":"pluginx","name":"X","version":"1.0.0","dependencies":[]}'
        );

        $manager = new PluginManager($tempDir);
        $first = $manager->discover();
        self::assertCount(1, $first);

        \mkdir($tempDir . '/pluginY', 0775, true);
        \file_put_contents(
            $tempDir . '/pluginY/plugin.json',
            '{"key":"pluginy","name":"Y","version":"1.0.0","dependencies":[]}'
        );

        $second = $manager->discover();

        self::assertCount(1, $second, 'discover() phai memoize, khong duoc thay plugin moi them sau lan goi dau.');
        self::assertSame($first, $second);

        \unlink($tempDir . '/pluginX/plugin.json');
        \unlink($tempDir . '/pluginY/plugin.json');
        \rmdir($tempDir . '/pluginX');
        \rmdir($tempDir . '/pluginY');
        \rmdir($tempDir);
    }

    public function testDiscoverThrowsOnDuplicateKey(): void
    {
        $manager = new PluginManager(self::DUPLICATE_FIXTURE_PATH);

        $this->expectException(PluginException::class);

        $manager->discover();
    }

    public function testDiscoverThrowsOnInvalidManifest(): void
    {
        $manager = new PluginManager(self::INVALID_FIXTURE_PATH);

        $this->expectException(PluginException::class);

        $manager->discover();
    }

    public function testResolveLoadOrderPutsDependencyBeforeDependent(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);

        $order = $manager->resolveLoadOrder(['pluginB', 'pluginA']);

        self::assertSame(['pluginA', 'pluginB'], \array_map(static fn ($descriptor) => $descriptor->key, $order));
    }

    public function testResolveLoadOrderThrowsWhenEnabledKeyNotDiscovered(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);

        $this->expectException(PluginNotFoundException::class);

        $manager->resolveLoadOrder(['does-not-exist']);
    }

    public function testResolveLoadOrderThrowsWhenDependencyNotEnabled(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);

        $this->expectException(PluginNotFoundException::class);

        $manager->resolveLoadOrder(['pluginB']);
    }

    public function testResolveLoadOrderThrowsCircularPluginDependencyException(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);

        $this->expectException(CircularPluginDependencyException::class);

        $manager->resolveLoadOrder(['circularA', 'circularB']);
    }

    public function testBootRegistersHooksInDependencyOrderAndReturnsLoadedKeys(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);
        $hook = new Hook();

        $loaded = $manager->boot($hook, ['pluginB', 'pluginA']);

        self::assertSame(['pluginA', 'pluginB'], $loaded);
        self::assertSame(['pluginA', 'pluginB'], $hook->apply('plugin.trace', []));
    }

    public function testBootIsolatesPluginThatThrowsAndContinuesWithOthers(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);
        $hook = new Hook();

        $loaded = $manager->boot($hook, ['broken', 'pluginA']);

        self::assertSame(['pluginA'], $loaded);
        self::assertSame(['pluginA'], $hook->apply('plugin.trace', []));
    }

    public function testGetFailuresReturnsExceptionForFailedPlugin(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);
        $hook = new Hook();

        $manager->boot($hook, ['broken']);

        $failures = $manager->getFailures();

        self::assertArrayHasKey('broken', $failures);
        self::assertInstanceOf(RuntimeException::class, $failures['broken']);
        self::assertSame('plugin-load-failure', $failures['broken']->getMessage());
    }

    public function testBootResetsFailuresOnEachCall(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);
        $hook = new Hook();

        $manager->boot($hook, ['broken']);
        self::assertCount(1, $manager->getFailures());

        $manager->boot($hook, ['pluginA']);
        self::assertCount(0, $manager->getFailures());
    }

    public function testBootSkipsPluginWithoutHooksFileWithoutError(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);
        $hook = new Hook();

        $loaded = $manager->boot($hook, ['nohooks']);

        self::assertSame(['nohooks'], $loaded);
        self::assertCount(0, $manager->getFailures());
    }

    public function testHooksFileOnlySeesHookVariableInScope(): void
    {
        $manager = new PluginManager(self::FIXTURE_PATH);
        $hook = new Hook();

        $manager->boot($hook, ['scopecheck']);

        $vars = $hook->apply('plugin.scope_vars', []);
        \sort($vars);

        self::assertSame(['hook', 'hooksFile'], $vars);
    }
}
