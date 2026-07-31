<?php

declare(strict_types=1);

namespace Core\Module;

final class ModuleNotFoundException extends ModuleException
{
    public static function forKey(string $key): self
    {
        return new self(\sprintf('Khong tim thay module "%s" trong danh sach da discover.', $key));
    }

    public static function forDependency(string $moduleKey, string $dependencyKey): self
    {
        return new self(\sprintf(
            'Module "%s" phu thuoc "%s" nhung "%s" chua duoc bat (enable). Phai bat ca module phu thuoc truoc.',
            $moduleKey,
            $dependencyKey,
            $dependencyKey
        ));
    }
}
