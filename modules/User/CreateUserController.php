<?php

declare(strict_types=1);

namespace Modules\User;

use Core\Authorization;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /users - tao user moi + gan role trong CUNG 1 transaction (khong orphan user). Role
 * validate BEN TRONG transaction (khong SELECT truoc), dung \InvalidArgumentException built-in
 * (khong RuntimeException - trung nhanh ke thua voi QueryException, xem Final Design CMS-037).
 */
final class CreateUserController
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
        if (!$this->authorization->can('user.create')) {
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
            'email' => 'required|email',
            'password' => 'required|string|min:8',
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

        $name = (string) $data['name'];
        $email = (string) $data['email'];
        $password = (string) $data['password'];
        $roleId = (int) $data['role_id'];
        $siteId = $this->tenantManager->id();

        try {
            $userId = $this->database->transaction(function (Database $db) use ($name, $email, $password, $roleId, $siteId): int {
                $role = $db->selectOne(
                    'SELECT id FROM roles WHERE id = ? AND (tenant_id IS NULL OR tenant_id = ?)',
                    [$roleId, $siteId]
                );

                if ($role === null) {
                    throw new \InvalidArgumentException('invalid-role');
                }

                $db->insert(
                    'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
                    [$name, $email, \password_hash($password, PASSWORD_DEFAULT), 'active']
                );
                $userId = (int) $db->connection()->lastInsertId();

                $db->insert(
                    'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
                    [$userId, $siteId, $roleId]
                );

                return $userId;
            });
        } catch (\InvalidArgumentException $exception) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Role khong hop le.',
                'errors' => [],
            ], 422);
        } catch (QueryException $exception) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Email da ton tai.',
                'errors' => [],
            ], 422);
        }

        return Response::json([
            'success' => true,
            'data' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'status' => 'active',
            ],
            'message' => '',
            'errors' => [],
        ], 201);
    }
}
