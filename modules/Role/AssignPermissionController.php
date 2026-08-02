<?php

declare(strict_types=1);

namespace Modules\Role;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /roles/{id}/permissions - gan 1 permission cho Tenant Role. System Role -> 403
 * "Permission modification forbidden" (Owner Decision 3). Idempotent - da gan roi thi tra
 * thanh cong, khong loi.
 */
final class AssignPermissionController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('role.assign_permission')) {
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
                'message' => 'Khong the sua permission cua system role.',
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

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'permission_id' => 'required|integer',
        ]);

        if ($result->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        $permissionId = (int) $data['permission_id'];

        $permission = $this->database->selectOne('SELECT id FROM permissions WHERE id = ?', [$permissionId]);

        if ($permission === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Permission khong hop le.',
                'errors' => [],
            ], 422);
        }

        $existing = $this->database->selectOne(
            'SELECT role_id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$roleId, $permissionId]
        );

        if ($existing === null) {
            $this->database->insert(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [$roleId, $permissionId]
            );
        }

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Gan permission thanh cong.',
            'errors' => [],
        ]);
    }
}
