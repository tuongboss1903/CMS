<?php

declare(strict_types=1);

namespace Core\Module;

use RuntimeException;

class ModuleException extends RuntimeException
{
    public static function cannotRead(string $file): self
    {
        return new self(\sprintf('Khong the doc file module.json: "%s".', $file));
    }

    public static function invalidManifest(string $file): self
    {
        return new self(\sprintf('module.json khong hop le (thieu key/name/version): "%s".', $file));
    }
}
