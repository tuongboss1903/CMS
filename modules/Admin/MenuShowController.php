<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/menus/{id} - copy logic buildTree()/attachChildren() tu Modules\Menu\ShowMenuController
 * y het (trung lap co chu dich, khong tai su dung cheo Module). Kem danh sach pages cua tenant
 * de lam dropdown "Page" trong form Add Item.
 */
final class MenuShowController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('menu.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $menuId = (int) $request->routeParam('id');

        $menu = $this->database->selectOne(
            'SELECT id, name, location_key FROM menus WHERE id = ? AND tenant_id = ?',
            [$menuId, $this->tenantManager->id()]
        );

        if ($menu === null) {
            return Response::html('404 Not Found', 404);
        }

        $items = $this->database->select(
            'SELECT id, parent_id, label, type, reference_id, url, target, sort_order
             FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC',
            [$menuId]
        );

        $pages = $this->database->select(
            'SELECT id, title FROM pages WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY title ASC',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.menus.show', [
            'menu' => $menu,
            'tree' => $this->buildTree($items),
            'pages' => $pages,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
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
