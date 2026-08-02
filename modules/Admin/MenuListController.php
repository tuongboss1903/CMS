<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/** GET /admin/menus - copy logic tu Modules\Menu\ListMenusController. */
final class MenuListController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('menu.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $menus = $this->database->select(
            'SELECT id, name, location_key FROM menus WHERE tenant_id = ?',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.menus.list', [
            'menus' => $menus,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
