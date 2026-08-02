<?php

declare(strict_types=1);

namespace Modules\Role;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;

/**
 * GET /permissions - danh muc permission toan he thong, khong tenant-scoped (dung
 * database-design.md: permissions la bang danh muc co dinh). Dung chung permission role.view.
 */
final class ListPermissionsController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('role.view')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $permissions = $this->database->select('SELECT id, `key`, description FROM permissions');

        return Response::json([
            'success' => true,
            'data' => $permissions,
            'message' => '',
            'errors' => [],
        ]);
    }
}
