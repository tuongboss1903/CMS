<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\View;

/**
 * GET /admin/login - render form dang nhap HTML. Khong Auth::check() redirect o day (da dang
 * nhap van xem duoc form - giu don gian, ngoai pham vi CMS-045).
 */
final class ShowLoginController
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $html = $this->view->render('admin.pages.login', [
            'errors' => [],
            'old' => ['email' => ''],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
