<?php

declare(strict_types=1);

namespace Core\Plugin;

final class PluginNotFoundException extends PluginException
{
    public static function forKey(string $key): self
    {
        return new self(\sprintf('Khong tim thay plugin "%s" trong danh sach da discover.', $key));
    }

    public static function forDependency(string $pluginKey, string $dependencyKey): self
    {
        return new self(\sprintf(
            'Plugin "%s" phu thuoc "%s" nhung "%s" chua duoc bat (enable). Phai bat plugin phu thuoc truoc.',
            $pluginKey,
            $dependencyKey,
            $dependencyKey
        ));
    }
}
