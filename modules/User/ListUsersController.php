<?php

declare(strict_types=1);

namespace Modules\User;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * GET /users - list user thuoc tenant hien tai (JOIN user_site_roles, khong co pagination -
 * xem Final Design CMS-037). Khong tra password.
 */
final class ListUsersController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('user.view')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $users = $this->database->select(
            'SELECT users.id, users.name, users.email, users.status
             FROM users
             INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
             WHERE user_site_roles.site_id = ?',
            [$this->tenantManager->id()]
        );

        return Response::json([
            'success' => true,
            'data' => $users,
            'message' => '',
            'errors' => [],
        ]);
    }
}
