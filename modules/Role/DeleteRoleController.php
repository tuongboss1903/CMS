<?php

declare(strict_types=1);

namespace Modules\Role;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * DELETE /roles/{id} - System Role -> 403 "Delete forbidden" (Owner Decision 3). Role dang duoc
 * gan cho user -> 409, khong the xoa. Kiem tra bang application-level COUNT() truoc khi DELETE
 * (khong dua vao FK RESTRICT cua DB - SQLite khong bat buoc PRAGMA foreign_keys = ON theo mac
 * dinh trong Database::connect(), da xac nhan tu CMS-030).
 */
final class DeleteRoleController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('role.delete')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $roleId = (int) $request->routeParam('id');
        $role = $this->database->selectOne('SELECT id, tenant_id FROM roles WHERE id = ?', [$roleId]);

        if ($role === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        if ($role['tenant_id'] === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'System role khong the xoa.',
                'errors' => [],
            ], 403);
        }

        if ((int) $role['tenant_id'] !== (int) $this->tenantManager->id()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $usage = $this->database->selectOne(
            'SELECT COUNT(*) as total FROM user_site_roles WHERE role_id = ?',
            [$roleId]
        );

        if ($usage !== null && (int) $usage['total'] > 0) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Role dang duoc su dung.',
                'errors' => [],
            ], 409);
        }

        $this->database->statement('DELETE FROM roles WHERE id = ?', [$roleId]);

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Xoa thanh cong.',
            'errors' => [],
        ]);
    }
}
