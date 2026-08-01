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
 * POST /pages/{id}/publish - doi status, published_at chi set LAN DAU (COALESCE), khong ghi de
 * khi publish lai - ap dung dung pattern PostService::publish() o database-design.md muc 6.3,
 * chuyen sang dung cho pages (CMS-040 chon pages truoc posts).
 */
final class PublishPageController
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
        if (!$this->authorization->can('page.publish')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $pageId = (int) $request->routeParam('id');

        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $this->tenantManager->id()]
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
            'status' => 'required|in:draft,published,scheduled',
        ]);

        if ($result->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
        }

        $status = (string) $data['status'];

        $this->database->statement(
            'UPDATE pages SET status = ?, published_at = COALESCE(published_at, CURRENT_TIMESTAMP) WHERE id = ?',
            [$status, $pageId]
        );

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Cap nhat trang thai thanh cong.',
            'errors' => [],
        ]);
    }
}
