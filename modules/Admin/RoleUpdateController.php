<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;
use Core\View;

/**
 * POST /admin/roles/{id} - khong PATCH (khong Method Spoofing, xem core/Http/Request.php).
 * Copy logic tu Modules\Role\EditRoleController.
 */
final class RoleUpdateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('role.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $roleId = (int) $request->routeParam('id');
        $role = $this->database->selectOne('SELECT id, tenant_id, name FROM roles WHERE id = ?', [$roleId]);

        if ($role === null) {
            return Response::html('404 Not Found', 404);
        }

        if ($role['tenant_id'] === null) {
            return Response::html('403 Forbidden', 403);
        }

        if ((int) $role['tenant_id'] !== (int) $this->tenantManager->id()) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'nullable|string',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($roleId, $result->errors(), $data);
        }

        if (!\array_key_exists('name', $data) || $data['name'] === null) {
            return $this->renderWithErrors($roleId, ['name' => ['Khong co du lieu de cap nhat.']], $data);
        }

        $this->database->statement('UPDATE roles SET name = ? WHERE id = ?', [(string) $data['name'], $roleId]);

        return Response::redirect('/admin/roles');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(int $roleId, array $errors, array $data): Response
    {
        $html = $this->view->render('admin.pages.roles.edit', [
            'breadcrumb_items' => [['label' => 'Vai trò & Phân quyền', 'url' => '/admin/roles'], ['label' => 'Sửa']],
            'role' => ['id' => $roleId],
            'errors' => $errors,
            'old' => ['name' => (string) ($data['name'] ?? '')],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
