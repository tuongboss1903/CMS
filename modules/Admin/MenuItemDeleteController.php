<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/menu-items/{id}/delete - khong DELETE method. Copy logic BFS gom id con chau tu
 * Modules\Menu\DeleteMenuItemController - 1 cau DELETE duy nhat (WHERE id IN (...)), khong
 * Database::transaction() (chi 1 cau SQL ghi, dung nguyen ly do goc).
 */
final class MenuItemDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('menu.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $itemId = (int) $request->routeParam('id');

        $item = $this->database->selectOne(
            'SELECT menu_items.id, menu_items.menu_id
             FROM menu_items
             INNER JOIN menus ON menus.id = menu_items.menu_id
             WHERE menu_items.id = ? AND menus.tenant_id = ?',
            [$itemId, $this->tenantManager->id()]
        );

        if ($item === null) {
            return Response::html('404 Not Found', 404);
        }

        $menuId = (int) $item['menu_id'];
        $idsToDelete = $this->collectSelfAndDescendantIds($itemId);

        $placeholders = \implode(',', \array_fill(0, \count($idsToDelete), '?'));
        $this->database->statement(
            "DELETE FROM menu_items WHERE id IN ({$placeholders})",
            $idsToDelete
        );

        return Response::redirect("/admin/menus/{$menuId}");
    }

    /** @return list<int> */
    private function collectSelfAndDescendantIds(int $itemId): array
    {
        $ids = [$itemId];
        $frontier = [$itemId];

        while ($frontier !== []) {
            $placeholders = \implode(',', \array_fill(0, \count($frontier), '?'));
            $children = $this->database->select(
                "SELECT id FROM menu_items WHERE parent_id IN ({$placeholders})",
                $frontier
            );

            $frontier = \array_map(static fn (array $row): int => (int) $row['id'], $children);
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
