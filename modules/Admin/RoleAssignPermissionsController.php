<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /admin/roles/{id}/permissions - copy logic tu Modules\Role\AssignPermissionController
 * (idempotent INSERT, khong transaction - dung 1 INSERT). Loi validate/permission khong hop le ->
 * redirect im lang ve lai trang permissions (hanh dong khong pha huy, cung tien le
 * UserAssignRoleController CMS-046). Chieu nguoc lai (go permission) xem
 * RoleRevokePermissionController - Owner Decision #1 CMS-047 cu (chi gan, khong go) da duoc thay
 * the boi yeu cau nang cap UX moi nhat.
 */
final class RoleAssignPermissionsController
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
            return Response::html('403 Forbidden', 403);
        }

        $roleId = (int) $request->routeParam('id');
        $role = $this->database->selectOne('SELECT id, tenant_id FROM roles WHERE id = ?', [$roleId]);

        if ($role === null) {
            return Response::html('404 Not Found', 404);
        }

        if ($role['tenant_id'] === null) {
            return Response::html('403 Forbidden', 403);
        }

        if ((int) $role['tenant_id'] !== (int) $this->tenantManager->id()) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();
        $redirectBack = Response::redirect("/admin/roles/{$roleId}/permissions");

        $result = $this->validator->validate($data, [
            'permission_id' => 'required|integer',
        ]);

        if ($result->fails()) {
            return $redirectBack;
        }

        $permissionId = (int) $data['permission_id'];

        $permission = $this->database->selectOne('SELECT id FROM permissions WHERE id = ?', [$permissionId]);

        if ($permission === null) {
            return $redirectBack;
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

        return $redirectBack;
    }
}
