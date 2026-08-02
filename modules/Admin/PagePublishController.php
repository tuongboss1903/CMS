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
 * POST /admin/pages/{id}/publish - copy logic tu Modules\Page\PublishPageController. Khong co
 * trang rieng de hien loi validate (form nam ngay trong list, giong UserAssignRoleController
 * CMS-046) - loi thi redirect im lang ve lai list.
 */
final class PagePublishController
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
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');

        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $this->tenantManager->id()]
        );

        if ($page === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'status' => 'required|in:draft,published,scheduled',
        ]);

        if ($result->fails()) {
            return Response::redirect('/admin/pages');
        }

        $this->database->statement(
            'UPDATE pages SET status = ?, published_at = COALESCE(published_at, CURRENT_TIMESTAMP) WHERE id = ?',
            [(string) $data['status'], $pageId]
        );

        return Response::redirect('/admin/pages');
    }
}
