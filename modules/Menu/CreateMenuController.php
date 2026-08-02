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

/** POST /menus - tao Menu moi. Trung (tenant_id, location_key) -> 422. */
final class CreateMenuController
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
        if (!$this->authorization->can('menu.create')) {
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
            'location_key' => 'required|string|max:50',
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
        $locationKey = (string) $data['location_key'];
        $siteId = $this->tenantManager->id();

        try {
            $this->database->insert(
                'INSERT INTO menus (tenant_id, name, location_key) VALUES (?, ?, ?)',
                [$siteId, $name, $locationKey]
            );
        } catch (QueryException $exception) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Location key da ton tai.',
                'errors' => [],
            ], 422);
        }

        $menuId = (int) $this->database->connection()->lastInsertId();

        return Response::json([
            'success' => true,
            'data' => ['id' => $menuId, 'name' => $name, 'location_key' => $locationKey],
            'message' => '',
            'errors' => [],
        ], 201);
    }
}
