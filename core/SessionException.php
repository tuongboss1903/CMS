<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

/** Nem khi goi get()/set()/... truoc khi start() - tranh dung $_SESSION chua duoc PHP khoi tao. */
final class SessionException extends RuntimeException
{
}
