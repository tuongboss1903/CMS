<?php

declare(strict_types=1);

namespace Modules\Menu;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/** POST /menus/{id}/items - them 1 item vao Menu. type=page can reference_id (page cung tenant); type=custom can url. */
final class CreateMenuItemController
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

        $menuId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $menu = $this->database->selectOne(
            'SELECT id FROM menus WHERE id = ? AND tenant_id = ?',
            [$menuId, $siteId]
        );

        if ($menu === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $data = $request->all();

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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        $type = (string) $data['type'];

        if ($type === 'page') {
            $referenceId = isset($data['reference_id']) ? (int) $data['reference_id'] : null;

            if ($referenceId === null || !$this->pageExistsInTenant($referenceId, $siteId)) {
                return Response::json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Page khong hop le.',
                    'errors' => [],
                ], 422);
            }

            $url = null;
        } else {
            $url = isset($data['url']) ? (string) $data['url'] : null;

            if ($url === null || $url === '') {
                return Response::json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Url khong hop le.',
                    'errors' => [],
                ], 422);
            }

            $referenceId = null;
        }

        $parentId = null;

        if (\array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $parentId = (int) $data['parent_id'];

            if (!$this->itemExistsInMenu($parentId, $menuId)) {
                return Response::json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Parent item khong hop le.',
                    'errors' => [],
                ], 422);
            }
        }

        $target = isset($data['target']) ? (string) $data['target'] : '_self';
        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $label = (string) $data['label'];

        $this->database->insert(
            'INSERT INTO menu_items (menu_id, parent_id, label, type, reference_id, url, target, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$menuId, $parentId, $label, $type, $referenceId, $url, $target, $sortOrder]
        );

        $itemId = (int) $this->database->connection()->lastInsertId();

        return Response::json([
            'success' => true,
            'data' => ['id' => $itemId, 'menu_id' => $menuId, 'label' => $label, 'type' => $type],
            'message' => '',
            'errors' => [],
        ], 201);
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
