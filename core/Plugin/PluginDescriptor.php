<?php

declare(strict_types=1);

namespace Core\Plugin;

/** Value object doc tu 1 file plugin.json. */
final class PluginDescriptor
{
    /** @param list<string> $dependencies */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $version,
        public readonly string $author,
        public readonly string $description,
        public readonly array $dependencies,
        public readonly string $path,
    ) {
    }
}
