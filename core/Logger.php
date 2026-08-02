<?php

declare(strict_types=1);

namespace Core;

/**
 * Ghi 1 dong co cau truc vao 1 file log co dinh (duong dan truyen qua constructor). Khong bat
 * exception, khong quyet dinh policy, khong biet HTTP/Database/Hook/ExceptionHandler/Config.
 * Khong level filtering - moi level deu duoc ghi nguyen ven nhu caller truyen vao.
 */
final class Logger
{
    public function __construct(private readonly string $logPath)
    {
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $directory = \dirname($this->logPath);

        if (!\is_dir($directory)) {
            @\mkdir($directory, 0775, true);
        }

        $contextSuffix = $context === [] ? '' : ' ' . \json_encode($context);

        $line = \sprintf(
            '[%s] %s: %s%s%s',
            \date('Y-m-d H:i:s'),
            $level,
            $message,
            $contextSuffix,
            PHP_EOL
        );

        @\file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
    }
}
