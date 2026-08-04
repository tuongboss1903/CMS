<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;

/** POST /system-admin/sites/{id}/activate - mo lai site da bi suspend/maintenance. */
final class SiteActivateController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Database $database,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $siteId = (int) $request->routeParam('id');
        $this->database->statement('UPDATE sites SET status = ? WHERE id = ?', ['active', $siteId]);

        return Response::redirect('/system-admin/sites');
    }
}
