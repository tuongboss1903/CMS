<?php

declare(strict_types=1);

namespace Core;

final class CircularDependencyException extends ContainerException
{
    /** @param list<string> $chain Day du chuoi id, phan tu dau va cuoi trung nhau (diem phat hien vong lap) */
    public function __construct(private readonly array $chain)
    {
        parent::__construct(sprintf('Circular dependency detected: %s', implode(' -> ', $chain)));
    }

    /** @return list<string> */
    public function getChain(): array
    {
        return $this->chain;
    }
}
