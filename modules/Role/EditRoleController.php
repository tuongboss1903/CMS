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
 * PATCH /roles/{id} - System Role (tenant_id NULL) -> 403 "Update forbidden" (Owner Decision 3).
 * Tenant Role thuoc site khac -> 404 (an danh, cung nguyen tac cross-tenant da dung o User Module).
 */
final class EditRoleController
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
        if (!$this->authorization->can('role.update')) {
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
                'message' => 'System role khong the sua.',
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
            'name' => 'nullable|string',
        ]);

        if ($result->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        if (!\array_key_exists('name', $data) || $data['name'] === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Khong co du lieu de cap nhat.',
                'errors' => [],
            ], 422);
        }

        $this->database->statement('UPDATE roles SET name = ? WHERE id = ?', [(string) $data['name'], $roleId]);

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Cap nhat thanh cong.',
            'errors' => [],
        ]);
    }
}
