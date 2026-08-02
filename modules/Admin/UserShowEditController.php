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

/** GET /admin/users/{id}/edit - 404 cho user khong thuoc tenant hien tai (khong 403). */
final class UserShowEditController
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
        if (!$this->authorization->can('user.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $userId = (int) $request->routeParam('id');

        $user = $this->database->selectOne(
            'SELECT users.id, users.name, users.email FROM users
             INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
             WHERE users.id = ? AND user_site_roles.site_id = ?',
            [$userId, $this->tenantManager->id()]
        );

        if ($user === null) {
            return Response::html('404 Not Found', 404);
        }

        $html = $this->view->render('admin.pages.users.edit', [
            'user' => $user,
            'errors' => [],
            'old' => ['name' => $user['name'], 'email' => $user['email']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
