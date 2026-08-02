<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Modules\Page\Actions\PageNotFoundException;
use Modules\Page\Actions\SetHomepageAction;

/**
 * POST /admin/pages/{id}/homepage - logic nghiep vu dung chung qua Actions\SetHomepageAction voi
 * Modules\Page\SetHomepageController (Pilot Action Class Pattern, Phase 6).
 */
final class PageSetHomepageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly SetHomepageAction $action,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');

        try {
            $this->action->execute($pageId);
        } catch (PageNotFoundException) {
            return Response::html('404 Not Found', 404);
        }

        return Response::redirect('/admin/pages');
    }
}
