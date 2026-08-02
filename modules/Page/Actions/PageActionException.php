<?php

declare(strict_types=1);

namespace Modules\Page\Actions;

use RuntimeException;

/**
 * Loi goc cua Page Actions (Pilot Action Class Pattern, Phase 6) - khong nem truc tiep, luon nem
 * 1 trong cac subclass cu the. Dung cung pattern voi Core\Database\DatabaseException.
 */
class PageActionException extends RuntimeException
{
}
