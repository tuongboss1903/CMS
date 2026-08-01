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
 * PATCH /users/{id} - chi sua user thuoc tenant hien tai (404 neu khong thuoc, khong 403 -
 * tranh xac nhan su ton tai cross-tenant). Cap nhat tung phan (chi field co truyen).
 */
final class EditUserController
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
        if (!$this->authorization->can('user.update')) {
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

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        if ($result->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        $fields = [];
        $bindings = [];

        if (\array_key_exists('name', $data) && $data['name'] !== null) {
            $fields[] = 'name = ?';
            $bindings[] = (string) $data['name'];
        }

        if (\array_key_exists('email', $data) && $data['email'] !== null) {
            $fields[] = 'email = ?';
            $bindings[] = (string) $data['email'];
        }

        if ($fields === []) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Khong co du lieu de cap nhat.',
                'errors' => [],
            ], 422);
        }

        $bindings[] = $userId;

        $this->database->statement(
            'UPDATE users SET ' . \implode(', ', $fields) . ' WHERE id = ?',
            $bindings
        );

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Cap nhat thanh cong.',
            'errors' => [],
        ]);
    }
}
