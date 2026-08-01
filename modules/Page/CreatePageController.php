<?php

declare(strict_types=1);

namespace Modules\Page;

use Core\Auth;
use Core\Authorization;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /pages - 1 cau INSERT duy nhat, khong can transaction (khac CreateUserController - khong
 * co bang phu nao phai ghi cung luc). content nhan dang array tu client, json_encode() truoc khi
 * luu vao cot TEXT (Owner Decision CMS-040: khong dung JSON column, Application layer tu xu ly).
 */
final class CreatePageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Auth $auth,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.create')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'title' => 'required|string',
            'slug' => 'required|string',
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

        $siteId = $this->tenantManager->id();

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
        } else {
            $parentId = null;
        }

        $title = (string) $data['title'];
        $slug = (string) $data['slug'];
        $content = \array_key_exists('content', $data) && $data['content'] !== null
            ? \json_encode($data['content'])
            : null;
        $template = \array_key_exists('template', $data) && $data['template'] !== null
            ? (string) $data['template']
            : null;

        try {
            $this->database->insert(
                'INSERT INTO pages (tenant_id, parent_id, title, slug, content, template, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$siteId, $parentId, $title, $slug, $content, $template, 'draft', $this->auth->id()]
            );
        } catch (QueryException $exception) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Slug da ton tai.',
                'errors' => [],
            ], 422);
        }

        $pageId = (int) $this->database->connection()->lastInsertId();

        return Response::json([
            'success' => true,
            'data' => [
                'id' => $pageId,
                'title' => $title,
                'slug' => $slug,
                'status' => 'draft',
            ],
            'message' => '',
            'errors' => [],
        ], 201);
    }
}
