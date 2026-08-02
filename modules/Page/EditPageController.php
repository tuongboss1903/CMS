<?php

declare(strict_types=1);

namespace Modules\Page;

use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Modules\Page\Actions\PageNotFoundException;
use Modules\Page\Actions\PageValidationException;
use Modules\Page\Actions\UpdatePageAction;

/**
 * PATCH /pages/{id} - logic nghiep vu chuyen vao Actions\UpdatePageAction (Pilot Action Class
 * Pattern, Phase 6).
 */
final class EditPageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly UpdatePageAction $action,
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
            $this->action->execute($pageId, $request->all());
        } catch (PageNotFoundException) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        } catch (PageValidationException $exception) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Cap nhat thanh cong.',
            'errors' => [],
        ]);
    }
}
