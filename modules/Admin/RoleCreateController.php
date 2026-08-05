<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;
use Core\View;

/**
 * POST /admin/roles - copy logic tu Modules\Role\CreateRoleController. Thanh cong redirect
 * /admin/roles, that bai render lai form (khong PRG, khong Session::flash).
 */
final class RoleCreateController
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
        if (!$this->authorization->can('role.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'required|string',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($result->errors(), $data);
        }

        $name = (string) $data['name'];
        $siteId = $this->tenantManager->id();

        try {
            $this->database->insert(
                'INSERT INTO roles (tenant_id, name) VALUES (?, ?)',
                [$siteId, $name]
            );
        } catch (QueryException $exception) {
            return $this->renderWithErrors(['name' => ['Ten role da ton tai.']], $data);
        }

        return Response::redirect('/admin/roles');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(array $errors, array $data): Response
    {
        $html = $this->view->render('admin.pages.roles.create', [
            'breadcrumb_items' => [['label' => 'Vai trò & Phân quyền', 'url' => '/admin/roles'], ['label' => 'Tạo mới']],
            'errors' => $errors,
            'old' => ['name' => (string) ($data['name'] ?? '')],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
