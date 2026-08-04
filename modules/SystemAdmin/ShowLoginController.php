<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\View;

/** GET /system-admin/login - render form dang nhap Super Admin. */
final class ShowLoginController
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $html = $this->view->render('system_admin.pages.login', [
            'errors' => [],
            'old' => ['email' => ''],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
