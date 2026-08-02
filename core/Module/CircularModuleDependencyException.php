<?php

declare(strict_types=1);

namespace Core\Module;

/** Cung mo hinh voi Core\CircularDependencyException (Container, CMS-003) - nhat quan cach bao loi. */
final class CircularModuleDependencyException extends ModuleException
{
    /** @param list<string> $chain */
    public function __construct(private readonly array $chain)
    {
        parent::__construct(\sprintf('Circular module dependency detected: %s', \implode(' -> ', $chain)));
    }

    /** @return list<string> */
    public function getChain(): array
    {
        return $this->chain;
    }
}
