<?php

declare(strict_types=1);

namespace Modules\Page;

use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Modules\Page\Actions\PageNotFoundException;
use Modules\Page\Actions\SetHomepageAction;

/**
 * POST /pages/{id}/homepage - logic nghiep vu chuyen vao Actions\SetHomepageAction (Pilot Action
 * Class Pattern, Phase 6).
 */
final class SetHomepageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly SetHomepageAction $action,
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
            'message' => 'Da dat lam trang chu.',
            'errors' => [],
        ]);
    }
}
