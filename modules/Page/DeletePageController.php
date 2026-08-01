<?php

declare(strict_types=1);

namespace Modules\Page;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * DELETE /pages/{id} - soft delete thuan: UPDATE deleted_at, khong DELETE that, khong restore/
 * trash trong CMS-040 (Owner Decision). Xoa page dang la homepage khong tu dong xu ly gi them -
 * rui ro chap nhan duoc, chua co bang chung can (ghi nhan trong Final Design).
 */
final class DeletePageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.delete')) {
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

        $this->database->statement(
            'UPDATE pages SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$pageId]
        );

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Xoa thanh cong.',
            'errors' => [],
        ]);
    }
}
