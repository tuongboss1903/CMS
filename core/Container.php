<?php

declare(strict_types=1);

namespace Core;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * DI Container tu viet (khong dung framework nen), tuong thich PSR-11.
 *
 * Nguyen tac bat buoc: object thuan PHP, khong static property/method o bat ky dau
 * trong class nay. Moi HTTP request phai tao 1 Container instance rieng (public/index.php,
 * task CMS-011) - "singleton" o day chi nghia la cache trong pham vi 1 instance Container,
 * khong phai global singleton toan app.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, array{concrete: Closure|string, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> Stack cac id dang duoc resolve dang do, de phat hien circular dependency */
    private array $resolving = [];

    /** @var array<class-string, ReflectionClass> */
    private array $reflectionCache = [];

    public function bind(string $id, Closure|string $concrete, bool $shared = false): void
    {
        $this->bindings[$id] = ['concrete' => $concrete, 'shared' => $shared];
        unset($this->instances[$id]);
    }

    public function singleton(string $id, Closure|string $concrete): void
    {
        $this->bind($id, $concrete, true);
    }

    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $object = $this->resolveWithCycleCheck($id, []);

        if (($this->bindings[$id]['shared'] ?? false) === true) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    /**
     * Giong get(), nhung luon tao instance moi (khong doc/ghi cache singleton) va cho phep
     * override tung tham so constructor theo ten qua $parameters.
     *
     * @param array<string, mixed> $parameters
     */
    public function make(string $id, array $parameters = []): mixed
    {
        return $this->resolveWithCycleCheck($id, $parameters);
    }

    /** @param array<string, mixed> $parameters */
    private function resolveWithCycleCheck(string $id, array $parameters): mixed
    {
        if (isset($this->resolving[$id])) {
            throw new CircularDependencyException([...array_keys($this->resolving), $id]);
        }

        $this->resolving[$id] = true;

        try {
            return $this->resolve($id, $parameters);
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /** @param array<string, mixed> $parameters */
    private function resolve(string $id, array $parameters): mixed
    {
        if (isset($this->bindings[$id])) {
            $concrete = $this->bindings[$id]['concrete'];

            if ($concrete instanceof Closure) {
                return $concrete($this);
            }

            return $this->autoWire($concrete, $parameters);
        }

        if (class_exists($id)) {
            return $this->autoWire($id, $parameters);
        }

        throw BindingNotFoundException::forId($id);
    }

    /**
     * Auto-wiring qua Reflection - CHI Constructor Injection (khong Property/Method Injection).
     *
     * @param class-string $className
     * @param array<string, mixed> $parameters
     */
    private function autoWire(string $className, array $parameters): object
    {
        $reflection = $this->reflectionCache[$className] ??= new ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf(
                'Khong the khoi tao "%s": la interface/abstract class chua duoc bind() toi 1 implementation cu the.',
                $className
            ));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = array_key_exists($parameter->getName(), $parameters)
                ? $parameters[$parameter->getName()]
                : $this->resolveParameter($parameter, $className);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveParameter(ReflectionParameter $parameter, string $forClass): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type !== null && $type->allowsNull()) {
            return null;
        }

        throw new ContainerException(sprintf(
            'Khong the tu dong resolve tham so "$%s" khi khoi tao "%s". Hay dang ky bind() thu cong bang Closure.',
            $parameter->getName(),
            $forClass
        ));
    }
}
