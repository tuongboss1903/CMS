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
 * POST /admin/users/{id} - khong PATCH (khong Method Spoofing, xem core/Http/Request.php).
 * Copy logic tu Modules\User\EditUserController. 404 cho cross-tenant (khong 403).
 */
final class UserUpdateController
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
        if (!$this->authorization->can('user.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $userId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $exists = $this->database->selectOne(
            'SELECT users.id FROM users
             INNER JOIN user_site_roles ON user_site_roles.user_id = users.id
             WHERE users.id = ? AND user_site_roles.site_id = ?',
            [$userId, $siteId]
        );

        if ($exists === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($userId, $result->errors(), $data);
        }

        $fields = [];
        $bindings = [];

        if (\array_key_exists('name', $data) && $data['name'] !== null) {
            $fields[] = 'name = ?';
            $bindings[] = (string) $data['name'];
        }

        if (\array_key_exists('email', $data) && $data['email'] !== null) {
            $fields[] = 'email = ?';
            $bindings[] = (string) $data['email'];
        }

        if ($fields === []) {
            return $this->renderWithErrors($userId, ['name' => ['Khong co du lieu de cap nhat.']], $data);
        }

        $bindings[] = $userId;

        $this->database->statement(
            'UPDATE users SET ' . \implode(', ', $fields) . ' WHERE id = ?',
            $bindings
        );

        return Response::redirect('/admin/users');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(int $userId, array $errors, array $data): Response
    {
        $html = $this->view->render('admin.pages.users.edit', [
            'breadcrumb_items' => [['label' => 'Người dùng', 'url' => '/admin/users'], ['label' => 'Sửa']],
            'user' => ['id' => $userId],
            'errors' => $errors,
            'old' => [
                'name' => (string) ($data['name'] ?? ''),
                'email' => (string) ($data['email'] ?? ''),
            ],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
