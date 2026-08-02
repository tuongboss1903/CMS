<?php

declare(strict_types=1);

namespace Modules\Role;

use Core\Authorization;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /roles - luon tao Tenant Role (tenant_id = site hien tai). Khong co input nao tao duoc
 * System Role qua Module - chi bin/bootstrap.php lam duoc dieu do (Owner Decision 3).
 */
final class CreateRoleController
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
        if (!$this->authorization->can('role.create')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'required|string',
        ]);

        if ($result->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        $name = (string) $data['name'];
        $siteId = $this->tenantManager->id();

        try {
            $this->database->insert(
                'INSERT INTO roles (tenant_id, name) VALUES (?, ?)',
                [$siteId, $name]
            );
        } catch (QueryException $exception) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Ten role da ton tai.',
                'errors' => [],
            ], 422);
        }

        $roleId = (int) $this->database->connection()->lastInsertId();

        return Response::json([
            'success' => true,
            'data' => ['id' => $roleId, 'name' => $name, 'system' => false],
            'message' => '',
            'errors' => [],
        ], 201);
    }
}
