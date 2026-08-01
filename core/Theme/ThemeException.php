<?php

declare(strict_types=1);

namespace Core\Theme;

use RuntimeException;

final class ThemeException extends RuntimeException
{
    public static function cannotRead(string $file): self
    {
        return new self(\sprintf('Khong the doc file theme.json: "%s".', $file));
    }

    public static function invalidManifest(string $file): self
    {
        return new self(\sprintf('theme.json khong hop le (thieu key/name/version): "%s".', $file));
    }
}
