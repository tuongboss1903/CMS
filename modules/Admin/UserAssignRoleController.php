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
 * POST /admin/users/{id}/role - copy logic tu Modules\User\AssignRoleController. Khong co
 * route/form rieng de hien thi loi (form nam ngay trong list.php) - loi/validate fail deu
 * redirect ve /admin/users (khong co trang rieng de render lai).
 *
 * CHI chap nhan Tenant Role (tenant_id = site hien tai) - KHONG cho gan System Role
 * (tenant_id NULL) qua endpoint nay, tranh leo thang dac quyen (Security Fix, dong bo
 * Modules\User\AssignRoleController).
 */
final class UserAssignRoleController
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
        if (!$this->authorization->can('user.assign_role')) {
            return Response::html('403 Forbidden', 403);
        }

        $userId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $exists = $this->database->selectOne(
            'SELECT id FROM user_site_roles WHERE user_id = ? AND site_id = ?',
            [$userId, $siteId]
        );

        if ($exists === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'role_id' => 'required|integer',
        ]);

        if ($result->fails()) {
            return Response::redirect('/admin/users');
        }

        $roleId = (int) $data['role_id'];

        $role = $this->database->selectOne(
            'SELECT id FROM roles WHERE id = ? AND tenant_id = ?',
            [$roleId, $siteId]
        );

        if ($role === null) {
            return Response::redirect('/admin/users');
        }

        $this->database->statement(
            'UPDATE user_site_roles SET role_id = ? WHERE user_id = ? AND site_id = ?',
            [$roleId, $userId, $siteId]
        );

        return Response::redirect('/admin/users');
    }
}
