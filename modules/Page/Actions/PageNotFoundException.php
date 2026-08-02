<?php

declare(strict_types=1);

namespace Modules\Page\Actions;

/**
 * Page khong ton tai / khong thuoc tenant hien tai / da xoa mem - ca 2 Controller (JSON 404,
 * Admin HTML 404) deu bat exception nay va tu quyet dinh dinh dang Response cua minh.
 */
final class PageNotFoundException extends PageActionException
{
}
