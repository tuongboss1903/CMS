<?php

declare(strict_types=1);

namespace Modules\Page;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /pages/{id}/homepage - dung page.update (khong page.set_homepage rieng, Owner Decision).
 * Verify ton tai TRUOC (404 neu khong, ngoai transaction - khac CreateUserController vi day chi
 * la pre-condition don gian, khong co rui ro orphan-row). Database::transaction() bao boc dung
 * 2 buoc UPDATE theo database-design.md muc 6.1.
 */
final class SetHomepageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
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

        $this->database->transaction(function (Database $db) use ($pageId, $siteId): void {
            $db->statement('UPDATE pages SET is_homepage = 0 WHERE tenant_id = ? AND is_homepage = 1', [$siteId]);
            $db->statement('UPDATE pages SET is_homepage = 1 WHERE id = ? AND tenant_id = ?', [$pageId, $siteId]);
        });

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Da dat lam trang chu.',
            'errors' => [],
        ]);
    }
}
