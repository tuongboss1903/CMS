<?php

declare(strict_types=1);

namespace Core\Theme;

/** Value object doc tu 1 file theme.json. */
final class ThemeDescriptor
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $version,
        public readonly string $screenshot,
        public readonly string $path,
    ) {
    }
}
