<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/** GET /admin/users/create - render form tao user + dropdown role. */
final class UserShowCreateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('user.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $roles = $this->database->select(
            'SELECT id, name FROM roles WHERE tenant_id IS NULL OR tenant_id = ?',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.users.create', [
            'roles' => $roles,
            'errors' => [],
            'old' => ['name' => '', 'email' => '', 'role_id' => ''],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
