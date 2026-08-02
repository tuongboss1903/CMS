<?php

declare(strict_types=1);

namespace Modules\Menu;

use Core\Authorization;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/** PATCH /menus/{id} - partial update name/location_key. 404 cross-tenant. */
final class UpdateMenuController
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
        if (!$this->authorization->can('menu.update')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $menuId = (int) $request->routeParam('id');

        $exists = $this->database->selectOne(
            'SELECT id FROM menus WHERE id = ? AND tenant_id = ?',
            [$menuId, $this->tenantManager->id()]
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
            'location_key' => 'nullable|string|max:50',
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

        if (\array_key_exists('location_key', $data) && $data['location_key'] !== null) {
            $fields[] = 'location_key = ?';
            $bindings[] = (string) $data['location_key'];
        }

        if ($fields === []) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Khong co du lieu de cap nhat.',
                'errors' => [],
            ], 422);
        }

        $bindings[] = $menuId;

        try {
            $this->database->statement(
                'UPDATE menus SET ' . \implode(', ', $fields) . ' WHERE id = ?',
                $bindings
            );
        } catch (QueryException $exception) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Location key da ton tai.',
                'errors' => [],
            ], 422);
        }

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Cap nhat thanh cong.',
            'errors' => [],
        ]);
    }
}
