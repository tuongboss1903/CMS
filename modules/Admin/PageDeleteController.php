<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\AuditLogger;
use Modules\Page\Actions\DeletePageAction;
use Modules\Page\Actions\PageNotFoundException;

/**
 * POST /admin/pages/{id}/delete - khong DELETE method. Logic nghiep vu dung chung qua
 * Actions\DeletePageAction voi Modules\Page\DeletePageController (Pilot Action Class Pattern,
 * Phase 6).
 *
 * Phase 16 (CMS-053): ghi "page.deleted" voi old_values la title/slug TRUOC khi xoa mem (Action
 * chi UPDATE deleted_at, du lieu van doc duoc truoc/sau execute() - lay truoc de co ten trang ro
 * rang trong log, tranh phai JOIN lai sau).
 */
final class PageDeleteController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly DeletePageAction $action,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.delete')) {
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');
        $before = $this->database->selectOne('SELECT title, slug FROM pages WHERE id = ?', [$pageId]);

        try {
            $this->action->execute($pageId);
        } catch (PageNotFoundException) {
            return Response::html('404 Not Found', 404);
        }

        $this->auditLogger->log($request, 'page.deleted', 'page', $pageId, $before, null);

        return Response::redirect('/admin/pages');
    }
}
