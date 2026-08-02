<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Modules\Page\Actions\PageNotFoundException;
use Modules\Page\Actions\PageValidationException;
use Modules\Page\Actions\PublishPageAction;

/**
 * POST /admin/pages/{id}/publish - logic nghiep vu dung chung qua Actions\PublishPageAction voi
 * Modules\Page\PublishPageController (Pilot Action Class Pattern, Phase 6). Khong co trang rieng
 * de hien loi validate (form nam ngay trong list) - loi thi redirect im lang ve lai list.
 */
final class PagePublishController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly PublishPageAction $action,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.publish')) {
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');

        try {
            $this->action->execute($pageId, $request->all());
        } catch (PageNotFoundException) {
            return Response::html('404 Not Found', 404);
        } catch (PageValidationException) {
            return Response::redirect('/admin/pages');
        }

        return Response::redirect('/admin/pages');
    }
}
