<?php

declare(strict_types=1);

namespace Core\Module;

/** Value object doc tu 1 file module.json. */
final class ModuleDescriptor
{
    /** @param list<string> $dependencies */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $version,
        public readonly array $dependencies,
        public readonly string $path,
    ) {
    }
}
