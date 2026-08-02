<?php

declare(strict_types=1);

namespace Modules\Menu;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * GET /menus/{id} - xem 1 Menu + cay menu_items (nested). Load toan bo item bang 1 query, dung
 * cay bang PHP (khong recursive SQL, khong N+1).
 */
final class ShowMenuController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('menu.view')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $menuId = (int) $request->routeParam('id');

        $menu = $this->database->selectOne(
            'SELECT id, name, location_key FROM menus WHERE id = ? AND tenant_id = ?',
            [$menuId, $this->tenantManager->id()]
        );

        if ($menu === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $items = $this->database->select(
            'SELECT id, parent_id, label, type, reference_id, url, target, sort_order
             FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC',
            [$menuId]
        );

        $menu['items'] = $this->buildTree($items);

        return Response::json([
            'success' => true,
            'data' => $menu,
            'message' => '',
            'errors' => [],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function buildTree(array $items): array
    {
        $byParent = [];

        foreach ($items as $item) {
            $parentKey = $item['parent_id'] === null ? '' : (string) $item['parent_id'];
            $byParent[$parentKey][] = $item;
        }

        return $this->attachChildren($byParent[''] ?? [], $byParent);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, list<array<string, mixed>>> $byParent
     * @return list<array<string, mixed>>
     */
    private function attachChildren(array $nodes, array $byParent): array
    {
        foreach ($nodes as &$node) {
            $node['children'] = $this->attachChildren($byParent[(string) $node['id']] ?? [], $byParent);
        }

        return $nodes;
    }
}
