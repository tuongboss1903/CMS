<?php

declare(strict_types=1);

namespace Core\View;

final class ViewNotFoundException extends ViewException
{
    /** @param list<string> $searchedPaths */
    public static function forTemplate(string $template, array $searchedPaths): self
    {
        if ($searchedPaths === []) {
            return new self(\sprintf('Ten view khong hop le: "%s".', $template));
        }

        return new self(\sprintf(
            'Khong tim thay view "%s". Da tim tai: %s',
            $template,
            \implode(', ', $searchedPaths)
        ));
    }
}
