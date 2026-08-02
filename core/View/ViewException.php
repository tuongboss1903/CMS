<?php

declare(strict_types=1);

namespace Core\View;

use RuntimeException;

/**
 * Loi cau truc template: goi endSection() khi chua section() dang mo, hoac section() long nhau
 * (chua ho tro - giu API don gian theo dung thiet ke CMS-005).
 */
class ViewException extends RuntimeException
{
}
