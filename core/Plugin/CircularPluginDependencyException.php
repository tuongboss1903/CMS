<?php

declare(strict_types=1);

namespace Core\Plugin;

final class CircularPluginDependencyException extends PluginException
{
    /** @param list<string> $chain */
    public function __construct(private readonly array $chain)
    {
        parent::__construct(\sprintf('Circular plugin dependency detected: %s', \implode(' -> ', $chain)));
    }

    /** @return list<string> */
    public function getChain(): array
    {
        return $this->chain;
    }
}
