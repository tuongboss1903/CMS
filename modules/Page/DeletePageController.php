<?php

declare(strict_types=1);

namespace Modules\Page;

use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Modules\Page\Actions\DeletePageAction;
use Modules\Page\Actions\PageNotFoundException;

/**
 * DELETE /pages/{id} - logic nghiep vu chuyen vao Actions\DeletePageAction (Pilot Action Class
 * Pattern, Phase 6).
 */
final class DeletePageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly DeletePageAction $action,
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

        try {
            $this->action->execute($pageId);
        } catch (PageNotFoundException) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Xoa thanh cong.',
            'errors' => [],
        ]);
    }
}
