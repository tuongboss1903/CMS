<?php

declare(strict_types=1);

namespace Core;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Loi resolve chung cua Container (khong phai loai "khong tim thay" - xem BindingNotFoundException).
 */
class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
