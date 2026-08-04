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
 * POST /admin/menu-items/{id} - copy logic tu Modules\Menu\UpdateMenuItemController (partial
 * update, xac dinh quyen so huu qua JOIN menus). Phuc vu 2 use case tu view show.php:
 * (1) form Edit item thuong (label/type/url/target) -> redirect ve /admin/menus/{menu_id};
 * (2) fetch() Drag-drop reorder (chi parent_id/sort_order) -> JSON, khong redirect.
 * Phan biet qua header X-Requested-With: JS tu set header nay khi goi fetch(), form thuong
 * (submit HTML) khong bao gio gui header nay - khong can Controller/route thu 2 trung logic.
 */
final class MenuItemUpdateController
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
        $isAjax = $request->ajax();

        if (!$this->authorization->can('menu.update')) {
            return $isAjax
                ? Response::json(['success' => false, 'message' => 'Forbidden.'], 403)
                : Response::html('403 Forbidden', 403);
        }

        $itemId = (int) $request->routeParam('id');

        $item = $this->database->selectOne(
            'SELECT menu_items.id, menu_items.menu_id, menu_items.type, menu_items.reference_id, menu_items.url
             FROM menu_items
             INNER JOIN menus ON menus.id = menu_items.menu_id
             WHERE menu_items.id = ? AND menus.tenant_id = ?',
            [$itemId, $this->tenantManager->id()]
        );

        if ($item === null) {
            return $isAjax
                ? Response::json(['success' => false, 'message' => 'Not Found'], 404)
                : Response::html('404 Not Found', 404);
        }

        $menuId = (int) $item['menu_id'];

        $data = $request->all();

        if (\array_key_exists('parent_id', $data) && $data['parent_id'] === '') {
            $data['parent_id'] = null;
        }

        $result = $this->validator->validate($data, [
            'label' => 'nullable|string',
            'type' => 'nullable|in:page,custom',
            'reference_id' => 'nullable|integer',
            'url' => 'nullable|string',
            'parent_id' => 'nullable|integer',
            'target' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            return $this->fail($isAjax, $menuId, 'Du lieu khong hop le.');
        }

        $fields = [];
        $bindings = [];

        if (\array_key_exists('label', $data) && $data['label'] !== null) {
            $fields[] = 'label = ?';
            $bindings[] = (string) $data['label'];
        }

        $typeOrReferenceOrUrlChanged = \array_key_exists('type', $data) || \array_key_exists('reference_id', $data) || \array_key_exists('url', $data);

        if ($typeOrReferenceOrUrlChanged) {
            $effectiveType = \array_key_exists('type', $data) && $data['type'] !== null ? (string) $data['type'] : (string) $item['type'];

            if ($effectiveType === 'page') {
                $effectiveReferenceId = \array_key_exists('reference_id', $data) && $data['reference_id'] !== null
                    ? (int) $data['reference_id']
                    : (isset($item['reference_id']) ? (int) $item['reference_id'] : null);

                if ($effectiveReferenceId === null || !$this->pageExistsInTenant($effectiveReferenceId, $this->tenantManager->id())) {
                    return $this->fail($isAjax, $menuId, 'Page khong hop le.');
                }

                $fields[] = 'type = ?';
                $bindings[] = 'page';
                $fields[] = 'reference_id = ?';
                $bindings[] = $effectiveReferenceId;
                $fields[] = 'url = ?';
                $bindings[] = null;
            } else {
                $effectiveUrl = \array_key_exists('url', $data) && $data['url'] !== null
                    ? (string) $data['url']
                    : (isset($item['url']) ? (string) $item['url'] : null);

                if ($effectiveUrl === null || $effectiveUrl === '') {
                    return $this->fail($isAjax, $menuId, 'Url khong hop le.');
                }

                $fields[] = 'type = ?';
                $bindings[] = 'custom';
                $fields[] = 'url = ?';
                $bindings[] = $effectiveUrl;
                $fields[] = 'reference_id = ?';
                $bindings[] = null;
            }
        }

        if (\array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $parentId = (int) $data['parent_id'];

            if ($parentId === $itemId) {
                return $this->fail($isAjax, $menuId, 'Parent item khong duoc trung chinh no.');
            }

            if (!$this->itemExistsInMenu($parentId, $menuId)) {
                return $this->fail($isAjax, $menuId, 'Parent item khong hop le.');
            }

            $fields[] = 'parent_id = ?';
            $bindings[] = $parentId;
        } elseif (\array_key_exists('parent_id', $data)) {
            $fields[] = 'parent_id = ?';
            $bindings[] = null;
        }

        if (\array_key_exists('target', $data) && $data['target'] !== null) {
            $fields[] = 'target = ?';
            $bindings[] = (string) $data['target'];
        }

        if (\array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $fields[] = 'sort_order = ?';
            $bindings[] = (int) $data['sort_order'];
        }

        if ($fields === []) {
            return $this->fail($isAjax, $menuId, 'Khong co du lieu de cap nhat.');
        }

        $bindings[] = $itemId;

        $this->database->statement(
            'UPDATE menu_items SET ' . \implode(', ', $fields) . ' WHERE id = ?',
            $bindings
        );

        if ($isAjax) {
            return Response::json(['success' => true, 'message' => 'Cap nhat thanh cong.']);
        }

        return Response::redirect("/admin/menus/{$menuId}");
    }

    private function fail(bool $isAjax, int $menuId, string $message): Response
    {
        if ($isAjax) {
            return Response::json(['success' => false, 'message' => $message], 422);
        }

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
