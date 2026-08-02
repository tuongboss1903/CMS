<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /admin/menus - copy logic tu Modules\Menu\CreateMenuController. Khong trang Create rieng
 * (form inline tren list.php, giong Media Upload Modal) - loi validate/trung location_key ->
 * silent-redirect ve /admin/menus, cung mau MediaUploadController.
 */
final class MenuCreateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('menu.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'required|string',
            'location_key' => 'required|string|max:50',
        ]);

        if ($result->fails()) {
            return Response::redirect('/admin/menus');
        }

        $siteId = $this->tenantManager->id();

        try {
            $this->database->insert(
                'INSERT INTO menus (tenant_id, name, location_key) VALUES (?, ?, ?)',
                [$siteId, (string) $data['name'], (string) $data['location_key']]
            );
        } catch (QueryException $exception) {
            return Response::redirect('/admin/menus');
        }

        return Response::redirect('/admin/menus');
    }
}
