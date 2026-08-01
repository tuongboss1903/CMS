<?php

declare(strict_types=1);

namespace Modules\User;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /users/{id}/lock - dung chung permission user.lock voi UnlockUserController (Owner
 * Decision CMS-037 - Lock/Unlock cung 1 capability). 404 neu user khong thuoc tenant hien tai.
 */
final class LockUserController
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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $userId = (int) $request->routeParam('id');

        $exists = $this->database->selectOne(
            'SELECT users.id FROM users
             INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
             WHERE users.id = ? AND user_site_roles.site_id = ?',
            [$userId, $this->tenantManager->id()]
        );

        if ($exists === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $this->database->statement('UPDATE users SET status = ? WHERE id = ?', ['locked', $userId]);

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Da khoa user.',
            'errors' => [],
        ]);
    }
}
