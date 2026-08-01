<?php

declare(strict_types=1);

namespace Modules\Page;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * GET /pages - scoped tenant hien tai, loai tru page da xoa mem (deleted_at). Khong tra 'content'
 * (noi dung lon, khong can cho danh sach - giong ListUsersController loai 'password').
 */
final class ListPagesController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.view')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $pages = $this->database->select(
            'SELECT id, parent_id, title, slug, status, is_homepage, published_at
             FROM pages
             WHERE tenant_id = ? AND deleted_at IS NULL',
            [$this->tenantManager->id()]
        );

        return Response::json([
            'success' => true,
            'data' => $pages,
            'message' => '',
            'errors' => [],
        ]);
    }
}
