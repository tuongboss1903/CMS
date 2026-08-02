<?php

declare(strict_types=1);

namespace Modules\Menu;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * DELETE /menu-items/{id} - xoa item VA toan bo nhanh con chau cua no (khong dua FK CASCADE that).
 * BFS gom id con chau chi dung SELECT (khong tinh la write) - cuoi cung chi 1 cau DELETE duy nhat
 * (WHERE id IN (...)) nen KHONG can Database::transaction() (chi 1 cau SQL ghi).
 */
final class DeleteMenuItemController
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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $itemId = (int) $request->routeParam('id');

        $item = $this->database->selectOne(
            'SELECT menu_items.id
             FROM menu_items
             INNER JOIN menus ON menus.id = menu_items.menu_id
             WHERE menu_items.id = ? AND menus.tenant_id = ?',
            [$itemId, $this->tenantManager->id()]
        );

        if ($item === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $idsToDelete = $this->collectSelfAndDescendantIds($itemId);

        $placeholders = \implode(',', \array_fill(0, \count($idsToDelete), '?'));
        $this->database->statement(
            "DELETE FROM menu_items WHERE id IN ({$placeholders})",
            $idsToDelete
        );

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Xoa thanh cong.',
            'errors' => [],
        ]);
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
