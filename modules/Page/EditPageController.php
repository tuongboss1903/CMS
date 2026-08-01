<?php

declare(strict_types=1);

namespace Modules\Page;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * PATCH /pages/{id} - cross-tenant hoac da xoa mem deu tra 404 (an danh, cung nguyen tac da
 * dung o User/Role Module). Cap nhat tung phan, content re-json_encode() neu co truyen.
 */
final class EditPageController
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
        if (!$this->authorization->can('page.update')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $pageId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $siteId]
        );

        if ($page === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'title' => 'nullable|string',
            'slug' => 'nullable|string',
            'content' => 'nullable|array',
            'template' => 'nullable|string',
            'parent_id' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        $fields = [];
        $bindings = [];

        if (\array_key_exists('title', $data) && $data['title'] !== null) {
            $fields[] = 'title = ?';
            $bindings[] = (string) $data['title'];
        }

        if (\array_key_exists('slug', $data) && $data['slug'] !== null) {
            $fields[] = 'slug = ?';
            $bindings[] = (string) $data['slug'];
        }

        if (\array_key_exists('content', $data) && $data['content'] !== null) {
            $fields[] = 'content = ?';
            $bindings[] = \json_encode($data['content']);
        }

        if (\array_key_exists('template', $data) && $data['template'] !== null) {
            $fields[] = 'template = ?';
            $bindings[] = (string) $data['template'];
        }

        if (\array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $parentId = (int) $data['parent_id'];
            $parent = $this->database->selectOne(
                'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
                [$parentId, $siteId]
            );

            if ($parent === null) {
                return Response::json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Parent page khong hop le.',
                    'errors' => [],
                ], 422);
            }

            $fields[] = 'parent_id = ?';
            $bindings[] = $parentId;
        }

        if ($fields === []) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Khong co du lieu de cap nhat.',
                'errors' => [],
            ], 422);
        }

        $bindings[] = $pageId;

        $this->database->statement(
            'UPDATE pages SET ' . \implode(', ', $fields) . ' WHERE id = ?',
            $bindings
        );

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Cap nhat thanh cong.',
            'errors' => [],
        ]);
    }
}
