<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /admin/menus/{id} - copy logic tu Modules\Menu\UpdateMenuController (partial update
 * name/location_key). Loi validate/trung location_key -> silent-redirect (sua inline tren
 * show.php, khong trang Edit rieng).
 */
final class MenuUpdateController
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
            return Response::html('403 Forbidden', 403);
        }

        $menuId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $exists = $this->database->selectOne(
            'SELECT id FROM menus WHERE id = ? AND tenant_id = ?',
            [$menuId, $siteId]
        );

        if ($exists === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'nullable|string',
            'location_key' => 'nullable|string|max:50',
        ]);

        if ($result->fails()) {
            return Response::redirect("/admin/menus/{$menuId}");
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
            return Response::redirect("/admin/menus/{$menuId}");
        }

        $bindings[] = $menuId;

        try {
            $this->database->statement(
                'UPDATE menus SET ' . \implode(', ', $fields) . ' WHERE id = ?',
                $bindings
            );
        } catch (QueryException $exception) {
            return Response::redirect("/admin/menus/{$menuId}");
        }

        return Response::redirect("/admin/menus/{$menuId}");
    }
}
