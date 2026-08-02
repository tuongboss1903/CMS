<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

/**
 * Stream wrapper gia lap "php://input" cho Unit Test - dang ky de vao vi tri wrapper "php" that,
 * cho phep test Request::fromGlobals() doc JSON body ma khong can 1 request HTTP that.
 */
final class PhpInputStreamStub
{
    /**
     * PHP Stream Wrapper API tu gan $stream->context khi dang ky wrapper - phai khai bao tuong
     * minh, khong de PHP tu tao dynamic property (Deprecated tu PHP 8.2+).
     */
    public mixed $context = null;

    public static string $content = '';

    private int $position = 0;

    public function stream_open(): bool
    {
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = \substr(self::$content, $this->position, $count);
        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen(self::$content);
    }

    /** @return array<int, int> */
    public function stream_stat(): array
    {
        return [];
    }
}
