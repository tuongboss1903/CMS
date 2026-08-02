<?php

declare(strict_types=1);

namespace Core\Plugin;

use RuntimeException;

class PluginException extends RuntimeException
{
    public static function cannotRead(string $file): self
    {
        return new self(\sprintf('Khong the doc file plugin.json: "%s".', $file));
    }

    public static function invalidManifest(string $file): self
    {
        return new self(\sprintf('plugin.json khong hop le (thieu key/name/version): "%s".', $file));
    }

    public static function duplicateKey(string $key, string $firstPath, string $secondPath): self
    {
        return new self(\sprintf(
            'Trung key plugin "%s" giua "%s" va "%s".',
            $key,
            $firstPath,
            $secondPath
        ));
    }
}
