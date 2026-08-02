<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/roles/{id}/delete - khong DELETE method (khong Method Spoofing). Copy logic tu
 * Modules\Role\DeleteRoleController. Owner Decision #2 CMS-047: loi tra HTML ro ly do (403/409),
 * khong redirect im lang - Delete la hanh dong pha huy du lieu.
 */
final class RoleDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('role.delete')) {
            return Response::html('403 Forbidden', 403);
        }

        $roleId = (int) $request->routeParam('id');
        $role = $this->database->selectOne('SELECT id, tenant_id FROM roles WHERE id = ?', [$roleId]);

        if ($role === null) {
            return Response::html('404 Not Found', 404);
        }

        if ($role['tenant_id'] === null) {
            return Response::html('403 Forbidden', 403);
        }

        if ((int) $role['tenant_id'] !== (int) $this->tenantManager->id()) {
            return Response::html('404 Not Found', 404);
        }

        $usage = $this->database->selectOne(
            'SELECT COUNT(*) as total FROM user_site_roles WHERE role_id = ?',
            [$roleId]
        );

        if ($usage !== null && (int) $usage['total'] > 0) {
            return Response::html('409 Role dang duoc su dung', 409);
        }

        $this->database->statement('DELETE FROM roles WHERE id = ?', [$roleId]);

        return Response::redirect('/admin/roles');
    }
}
