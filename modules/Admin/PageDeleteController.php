<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Modules\Page\Actions\DeletePageAction;
use Modules\Page\Actions\PageNotFoundException;

/**
 * POST /admin/pages/{id}/delete - khong DELETE method. Logic nghiep vu dung chung qua
 * Actions\DeletePageAction voi Modules\Page\DeletePageController (Pilot Action Class Pattern,
 * Phase 6).
 */
final class PageDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly DeletePageAction $action,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.delete')) {
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
