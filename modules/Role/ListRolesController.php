<?php

declare(strict_types=1);

namespace Modules\Role;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * GET /roles - tra ca System Role (tenant_id NULL) lan Tenant Role cua site hien tai, dung
 * "View allowed" cho System Role (Owner Decision 3, CMS Foundation Completion).
 */
final class ListRolesController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('role.view')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $roles = $this->database->select(
            'SELECT id, tenant_id, name FROM roles WHERE tenant_id IS NULL OR tenant_id = ?',
            [$this->tenantManager->id()]
        );

        $data = \array_map(static function (array $role): array {
            return [
                'id' => (int) $role['id'],
                'name' => $role['name'],
                'system' => $role['tenant_id'] === null,
            ];
        }, $roles);

        return Response::json([
            'success' => true,
            'data' => $data,
            'message' => '',
            'errors' => [],
        ]);
    }
}
