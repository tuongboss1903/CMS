<?php

declare(strict_types=1);

use Core\I18n\Translator;

/**
 * DUY NHAT file dinh nghia global function trong du an - nap qua composer "autoload.files"
 * (xem composer.json), khong autoload PSR-4. Chi chua __() (Phase 13, CMS-050) - khong them
 * helper nao khac vao day tru khi co ly do tuong tu (can goi tu View template, khong the
 * Dependency Injection).
 */

if (!\function_exists('__')) {
    /**
     * Dich static UI text - xem docblock Core\I18n\Translator ve static holder dung rieng cho
     * helper nay. @param array<string, scalar> $replace
     */
    function __(string $key, array $replace = []): string
    {
        return Translator::globalInstance()->translate($key, $replace);
    }
}
