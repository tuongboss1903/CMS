<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /admin/menus/{id}/items - copy logic tu Modules\Menu\CreateMenuItemController (type=page
 * can reference_id hop le cung tenant; type=custom can url). Loi -> silent-redirect ve
 * /admin/menus/{id} (form Add Item nam ngay tren show.php, khong trang rieng).
 */
final class MenuItemCreateController
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

        $menu = $this->database->selectOne(
            'SELECT id FROM menus WHERE id = ? AND tenant_id = ?',
            [$menuId, $siteId]
        );

        if ($menu === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        if (\array_key_exists('parent_id', $data) && $data['parent_id'] === '') {
            $data['parent_id'] = null;
        }

        $result = $this->validator->validate($data, [
            'label' => 'required|string',
            'type' => 'required|in:page,custom',
            'reference_id' => 'nullable|integer',
            'url' => 'nullable|string',
            'parent_id' => 'nullable|integer',
            'target' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            return Response::redirect("/admin/menus/{$menuId}");
        }

        $type = (string) $data['type'];

        if ($type === 'page') {
            $referenceId = isset($data['reference_id']) ? (int) $data['reference_id'] : null;

            if ($referenceId === null || !$this->pageExistsInTenant($referenceId, $siteId)) {
                return Response::redirect("/admin/menus/{$menuId}");
            }

            $url = null;
        } else {
            $url = isset($data['url']) ? (string) $data['url'] : null;

            if ($url === null || $url === '') {
                return Response::redirect("/admin/menus/{$menuId}");
            }

            $referenceId = null;
        }

        $parentId = null;

        if (\array_key_exists('parent_id', $data) && $data['parent_id'] !== null && $data['parent_id'] !== '') {
            $parentId = (int) $data['parent_id'];

            if (!$this->itemExistsInMenu($parentId, $menuId)) {
                return Response::redirect("/admin/menus/{$menuId}");
            }
        }

        $target = isset($data['target']) && $data['target'] !== '' ? (string) $data['target'] : '_self';
        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $label = (string) $data['label'];

        $this->database->insert(
            'INSERT INTO menu_items (menu_id, parent_id, label, type, reference_id, url, target, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$menuId, $parentId, $label, $type, $referenceId, $url, $target, $sortOrder]
        );

        return Response::redirect("/admin/menus/{$menuId}");
    }

    private function pageExistsInTenant(int $pageId, int|string|null $tenantId): bool
    {
        $row = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $tenantId]
        );

        return $row !== null;
    }

    private function itemExistsInMenu(int $itemId, int $menuId): bool
    {
        $row = $this->database->selectOne(
            'SELECT id FROM menu_items WHERE id = ? AND menu_id = ?',
            [$itemId, $menuId]
        );

        return $row !== null;
    }
}
