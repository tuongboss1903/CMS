<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\View;

/** GET /admin/roles/create - render form tao role. Luon tao Tenant Role (khong the tao System Role qua UI). */
final class RoleShowCreateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('role.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $html = $this->view->render('admin.pages.roles.create', [
            'errors' => [],
            'old' => ['name' => ''],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
