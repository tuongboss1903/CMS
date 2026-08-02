<?php

declare(strict_types=1);

namespace Modules\Page\Actions;

/**
 * Du lieu khong hop le (Validator that bai, slug trung, parent_id sai, hoac khong co field nao
 * de cap nhat) - ca 2 Controller (JSON 422, Admin redirect/render lai form) deu bat exception nay.
 */
final class PageValidationException extends PageActionException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(string $message, private readonly array $errors = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
