<?php

declare(strict_types=1);

namespace Modules\Dashboard;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * GET /dashboard - foundation toi gian, chi user_count + role_count, scoped tenant hien tai.
 * Chua UI, chua data source nao khac (Content/Media chua ton tai).
 */
final class DashboardController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('dashboard.view')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $siteId = $this->tenantManager->id();

        $userCount = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM users
             INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
             WHERE user_site_roles.site_id = ?',
            [$siteId]
        );

        $roleCount = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM roles WHERE tenant_id IS NULL OR tenant_id = ?',
            [$siteId]
        );

        return Response::json([
            'success' => true,
            'data' => [
                'user_count' => (int) $userCount['count'],
                'role_count' => (int) $roleCount['count'],
            ],
            'message' => '',
            'errors' => [],
        ]);
    }
}
