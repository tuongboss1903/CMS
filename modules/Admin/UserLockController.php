<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/** POST /admin/users/{id}/lock - copy logic tu Modules\User\LockUserController. */
final class UserLockController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('user.lock')) {
            return Response::html('403 Forbidden', 403);
        }

        $userId = (int) $request->routeParam('id');

        $exists = $this->database->selectOne(
            'SELECT users.id FROM users
             INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
             WHERE users.id = ? AND user_site_roles.site_id = ?',
            [$userId, $this->tenantManager->id()]
        );

        if ($exists === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->database->statement('UPDATE users SET status = ? WHERE id = ?', ['locked', $userId]);

        return Response::redirect('/admin/users');
    }
}
