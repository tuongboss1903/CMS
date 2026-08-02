<?php

declare(strict_types=1);

namespace Modules\Menu;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * PATCH /menu-items/{id} - partial update. Xac dinh quyen so huu qua JOIN menus (tenant_id).
 * parent_id khong duoc bang chinh id cua item (self-parent -> 422).
 */
final class UpdateMenuItemController
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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $data = $request->all();

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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        $menuId = (int) $item['menu_id'];
        $fields = [];
        $bindings = [];

        if (\array_key_exists('label', $data) && $data['label'] !== null) {
            $fields[] = 'label = ?';
            $bindings[] = (string) $data['label'];
        }

        $effectiveType = \array_key_exists('type', $data) && $data['type'] !== null ? (string) $data['type'] : (string) $item['type'];
        $typeOrReferenceOrUrlChanged = \array_key_exists('type', $data) || \array_key_exists('reference_id', $data) || \array_key_exists('url', $data);

        if ($typeOrReferenceOrUrlChanged) {
            if ($effectiveType === 'page') {
                $effectiveReferenceId = \array_key_exists('reference_id', $data) && $data['reference_id'] !== null
                    ? (int) $data['reference_id']
                    : (isset($item['reference_id']) ? (int) $item['reference_id'] : null);

                if ($effectiveReferenceId === null || !$this->pageExistsInTenant($effectiveReferenceId, $this->tenantManager->id())) {
                    return Response::json([
                        'success' => false,
                        'data' => null,
                        'message' => 'Page khong hop le.',
                        'errors' => [],
                    ], 422);
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
                    return Response::json([
                        'success' => false,
                        'data' => null,
                        'message' => 'Url khong hop le.',
                        'errors' => [],
                    ], 422);
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
                return Response::json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Parent item khong duoc trung chinh no.',
                    'errors' => [],
                ], 422);
            }

            if (!$this->itemExistsInMenu($parentId, $menuId)) {
                return Response::json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Parent item khong hop le.',
                    'errors' => [],
                ], 422);
            }

            $fields[] = 'parent_id = ?';
            $bindings[] = $parentId;
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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Khong co du lieu de cap nhat.',
                'errors' => [],
            ], 422);
        }

        $bindings[] = $itemId;

        $this->database->statement(
            'UPDATE menu_items SET ' . \implode(', ', $fields) . ' WHERE id = ?',
            $bindings
        );

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Cap nhat thanh cong.',
            'errors' => [],
        ]);
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
