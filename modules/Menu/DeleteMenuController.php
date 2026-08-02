<?php

declare(strict_types=1);

namespace Modules\Menu;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * DELETE /menus/{id} - khong dua vao FK CASCADE that (SQLite test khong enforce mac dinh).
 * Controller tu xoa menu_items truoc roi menus, bọc Database::transaction() (2 cau DELETE lien quan).
 */
final class DeleteMenuController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('menu.delete')) {
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

        $this->database->transaction(function (Database $db) use ($menuId): void {
            $db->statement('DELETE FROM menu_items WHERE menu_id = ?', [$menuId]);
            $db->statement('DELETE FROM menus WHERE id = ?', [$menuId]);
        });

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Xoa thanh cong.',
            'errors' => [],
        ]);
    }
}
