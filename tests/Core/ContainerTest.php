<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\BindingNotFoundException;
use Core\CircularDependencyException;
use Core\Container;
use Core\ContainerException;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\ConcreteFoo;
use Tests\Fixtures\DependsOnFoo;
use Tests\Fixtures\FooInterface;
use Tests\Fixtures\NeedsScalar;
use Tests\Fixtures\NeedsScalarWithDefault;
use Tests\Fixtures\NoConstructorClass;
use Tests\Fixtures\PermissionServiceFixture;
use Tests\Fixtures\RoleServiceFixture;
use Tests\Fixtures\UserServiceFixture;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testResolvesConcreteClassWithoutBinding(): void
    {
        $instance = $this->container->get(NoConstructorClass::class);

        self::assertInstanceOf(NoConstructorClass::class, $instance);
    }

    public function testResolvesInterfaceViaBinding(): void
    {
        $this->container->bind(FooInterface::class, ConcreteFoo::class);

        $instance = $this->container->get(FooInterface::class);

        self::assertInstanceOf(ConcreteFoo::class, $instance);
    }

    public function testSingletonReturnsSameInstanceOnEachGet(): void
    {
        $this->container->singleton(ConcreteFoo::class, ConcreteFoo::class);

        self::assertSame($this->container->get(ConcreteFoo::class), $this->container->get(ConcreteFoo::class));
    }

    public function testNonSharedBindingReturnsDifferentInstances(): void
    {
        self::assertNotSame($this->container->get(ConcreteFoo::class), $this->container->get(ConcreteFoo::class));
    }

    public function testAutoWiringResolvesConstructorDependencyRecursively(): void
    {
        $this->container->bind(FooInterface::class, ConcreteFoo::class);

        $instance = $this->container->get(DependsOnFoo::class);

        self::assertInstanceOf(ConcreteFoo::class, $instance->foo);
    }

    public function testCircularDependencyThrowsWithFullChain(): void
    {
        try {
            $this->container->get(UserServiceFixture::class);
            self::fail('Expected CircularDependencyException was not thrown.');
        } catch (CircularDependencyException $exception) {
            self::assertSame(
                [
                    UserServiceFixture::class,
                    RoleServiceFixture::class,
                    PermissionServiceFixture::class,
                    UserServiceFixture::class,
                ],
                $exception->getChain()
            );
        }
    }

    public function testBindingNotFoundThrowsForUnknownId(): void
    {
        $this->expectException(BindingNotFoundException::class);

        $this->container->get('this-id-does-not-exist');
    }

    public function testInstanceRegistersExistingObjectWithoutAutoWiring(): void
    {
        $preBuilt = new ConcreteFoo();

        $this->container->instance(FooInterface::class, $preBuilt);

        self::assertSame($preBuilt, $this->container->get(FooInterface::class));
    }

    public function testScalarParameterWithoutDefaultThrowsContainerExceptionNotNotFound(): void
    {
        try {
            $this->container->get(NeedsScalar::class);
            self::fail('Expected ContainerException was not thrown.');
        } catch (ContainerException $exception) {
            self::assertNotInstanceOf(BindingNotFoundException::class, $exception);
        }
    }

    public function testScalarParameterUsesDefaultValueWhenAvailable(): void
    {
        $instance = $this->container->get(NeedsScalarWithDefault::class);

        self::assertSame('default', $instance->value);
    }

    public function testMakeAllowsOverridingConstructorParameterByName(): void
    {
        $instance = $this->container->make(NeedsScalar::class, ['value' => 'overridden']);

        self::assertSame('overridden', $instance->value);
    }

    public function testHasReturnsTrueForKnownIdAndFalseForUnknownId(): void
    {
        $this->container->bind(FooInterface::class, ConcreteFoo::class);

        self::assertTrue($this->container->has(FooInterface::class));
        self::assertFalse($this->container->has('unknown-id'));
    }
}
