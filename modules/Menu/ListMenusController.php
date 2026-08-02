<?php

declare(strict_types=1);

namespace Modules\Menu;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/** GET /menus - list scoped tenant hien tai, khong kem item (nhe, giong ListPagesController). */
final class ListMenusController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('menu.view')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $menus = $this->database->select(
            'SELECT id, name, location_key FROM menus WHERE tenant_id = ?',
            [$this->tenantManager->id()]
        );

        return Response::json([
            'success' => true,
            'data' => $menus,
            'message' => '',
            'errors' => [],
        ]);
    }
}
