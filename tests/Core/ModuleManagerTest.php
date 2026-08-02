<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Container;
use Core\Http\Request;
use Core\Module\CircularModuleDependencyException;
use Core\Module\ModuleException;
use Core\Module\ModuleNotFoundException;
use Core\ModuleManager;
use Core\Router;
use PHPUnit\Framework\TestCase;

final class ModuleManagerTest extends TestCase
{
    private ModuleManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ModuleManager(__DIR__ . '/../Fixtures/Modules');
    }

    public function testDiscoverFindsAllModulesInDirectory(): void
    {
        $descriptors = $this->manager->discover();

        self::assertArrayHasKey('alpha', $descriptors);
        self::assertArrayHasKey('beta', $descriptors);
        self::assertArrayHasKey('circular1', $descriptors);
        self::assertArrayHasKey('circular2', $descriptors);
        self::assertArrayHasKey('noroutes', $descriptors);
        self::assertCount(5, $descriptors);
    }

    public function testDiscoverParsesDependenciesFromManifest(): void
    {
        $descriptors = $this->manager->discover();

        self::assertSame(['alpha'], $descriptors['beta']->dependencies);
        self::assertSame([], $descriptors['alpha']->dependencies);
    }

    public function testResolveLoadOrderPutsDependencyBeforeDependent(): void
    {
        $order = \array_map(
            static fn ($descriptor): string => $descriptor->key,
            $this->manager->resolveLoadOrder(['alpha', 'beta'])
        );

        self::assertSame(['alpha', 'beta'], $order);
    }

    public function testResolveLoadOrderReordersRegardlessOfInputOrder(): void
    {
        // enabledKeys liet ke beta TRUOC alpha - ket qua van phai dung thu tu dependency.
        $order = \array_map(
            static fn ($descriptor): string => $descriptor->key,
            $this->manager->resolveLoadOrder(['beta', 'alpha'])
        );

        self::assertSame(['alpha', 'beta'], $order);
    }

    public function testResolveLoadOrderThrowsWhenEnabledModuleNotDiscovered(): void
    {
        $this->expectException(ModuleNotFoundException::class);

        $this->manager->resolveLoadOrder(['does-not-exist']);
    }

    public function testResolveLoadOrderThrowsWhenDependencyNotEnabled(): void
    {
        $this->expectException(ModuleNotFoundException::class);

        // 'beta' phu thuoc 'alpha' nhung alpha khong nam trong danh sach bat.
        $this->manager->resolveLoadOrder(['beta']);
    }

    public function testResolveLoadOrderThrowsCircularModuleDependencyException(): void
    {
        try {
            $this->manager->resolveLoadOrder(['circular1', 'circular2']);
            self::fail('Expected CircularModuleDependencyException was not thrown.');
        } catch (CircularModuleDependencyException $exception) {
            self::assertSame(['circular1', 'circular2', 'circular1'], $exception->getChain());
        }
    }

    public function testBootLoadsRoutesInDependencyOrderAndReturnsLoadedKeys(): void
    {
        $router = new Router(new \Core\Container());

        $loaded = $this->manager->boot($router, ['alpha', 'beta']);

        self::assertSame(['alpha', 'beta'], $loaded);
        self::assertSame('alpha', $router->dispatch(new Request('GET', '/alpha', 'example.com'))->getBody());
        self::assertSame('beta', $router->dispatch(new Request('GET', '/beta', 'example.com'))->getBody());
    }

    public function testBootSkipsModuleWithoutRoutesFileWithoutError(): void
    {
        $router = new Router(new \Core\Container());

        $loaded = $this->manager->boot($router, ['noroutes']);

        self::assertSame(['noroutes'], $loaded);
    }

    public function testInvalidManifestThrowsModuleException(): void
    {
        $manager = new ModuleManager(__DIR__ . '/../Fixtures/ModulesInvalid');

        $this->expectException(ModuleException::class);

        $manager->discover();
    }
}
