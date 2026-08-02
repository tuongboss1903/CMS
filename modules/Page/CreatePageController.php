<?php

declare(strict_types=1);

namespace Modules\Page;

use Core\Auth;
use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Modules\Page\Actions\CreatePageAction;
use Modules\Page\Actions\PageValidationException;

/**
 * POST /pages - logic nghiep vu (validate + INSERT) da chuyen vao Actions\CreatePageAction
 * (Pilot Action Class Pattern, Phase 6) - Controller chi con trach nhiem HTTP: check permission,
 * doc Request, goi Action, dinh dang Response JSON.
 */
final class CreatePageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Auth $auth,
        private readonly CreatePageAction $action,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.create')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        try {
            $page = $this->action->execute($request->all(), $this->auth->id());
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
            'data' => $page,
            'message' => '',
            'errors' => [],
        ], 201);
    }
}
