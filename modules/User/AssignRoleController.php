<?php

declare(strict_types=1);

namespace Modules\User;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /users/{id}/role - chi doi role cua user DA thuoc tenant hien tai (khong tao moi
 * user_site_roles - viec do thuoc CreateUserController, xem Final Design CMS-037).
 *
 * CHI chap nhan Tenant Role (tenant_id = site hien tai) - KHONG cho gan System Role
 * (tenant_id NULL, vd "Admin" toan quyen tao o bin/bootstrap.php) qua endpoint nay, tranh
 * leo thang dac quyen: "user.assign_role" la quyen hep (doi role trong pham vi tenant),
 * khong duoc phep cap toan quyen he thong (Security Fix).
 */
final class AssignRoleController
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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $userId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $exists = $this->database->selectOne(
            'SELECT id FROM user_site_roles WHERE user_id = ? AND site_id = ?',
            [$userId, $siteId]
        );

        if ($exists === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'role_id' => 'required|integer',
        ]);

        if ($result->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        $roleId = (int) $data['role_id'];

        $role = $this->database->selectOne(
            'SELECT id FROM roles WHERE id = ? AND tenant_id = ?',
            [$roleId, $siteId]
        );

        if ($role === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Role khong hop le.',
                'errors' => [],
            ], 422);
        }

        $this->database->statement(
            'UPDATE user_site_roles SET role_id = ? WHERE user_id = ? AND site_id = ?',
            [$roleId, $userId, $siteId]
        );

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Cap nhat role thanh cong.',
            'errors' => [],
        ]);
    }
}
