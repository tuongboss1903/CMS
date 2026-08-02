<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/menus/{id}/delete - khong DELETE method. Copy logic tu Modules\Menu\DeleteMenuController
 * (Database::transaction() - 2 cau DELETE lien quan menu_items truoc, menus sau).
 */
final class MenuDeleteController
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
            return Response::html('403 Forbidden', 403);
        }

        $menuId = (int) $request->routeParam('id');

        $exists = $this->database->selectOne(
            'SELECT id FROM menus WHERE id = ? AND tenant_id = ?',
            [$menuId, $this->tenantManager->id()]
        );

        if ($exists === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->database->transaction(function (Database $db) use ($menuId): void {
            $db->statement('DELETE FROM menu_items WHERE menu_id = ?', [$menuId]);
            $db->statement('DELETE FROM menus WHERE id = ?', [$menuId]);
        });

        return Response::redirect('/admin/menus');
    }
}
