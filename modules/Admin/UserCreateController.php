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
 * POST /admin/users - copy nguyen transaction logic tu Modules\User\CreateUserController
 * (Owner Decision CMS-046 Phuong an A - khong tao Service Layer chi de tranh duplication;
 * Technical Debt ghi nhan neu sau nay co nhieu UI/API consumer). Khac ban JSON: thanh cong
 * redirect /admin/users, that bai render lai form (khong PRG, khong Session::flash).
 */
final class UserCreateController
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
        if (!$this->authorization->can('user.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|integer',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($result->errors(), $data);
        }

        $name = (string) $data['name'];
        $email = (string) $data['email'];
        $password = (string) $data['password'];
        $roleId = (int) $data['role_id'];
        $siteId = $this->tenantManager->id();

        if ($this->exceedsUserQuota($siteId)) {
            return $this->renderWithErrors(['plan' => ['Da dat gioi han so User theo goi dich vu hien tai.']], $data);
        }

        try {
            $this->database->transaction(function (Database $db) use ($name, $email, $password, $roleId, $siteId): int {
                $role = $db->selectOne(
                    'SELECT id FROM roles WHERE id = ? AND (tenant_id IS NULL OR tenant_id = ?)',
                    [$roleId, $siteId]
                );

                if ($role === null) {
                    throw new \InvalidArgumentException('invalid-role');
                }

                $db->insert(
                    'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
                    [$name, $email, \password_hash($password, PASSWORD_DEFAULT), 'active']
                );
                $userId = (int) $db->connection()->lastInsertId();

                $db->insert(
                    'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
                    [$userId, $siteId, $roleId]
                );

                return $userId;
            });
        } catch (\InvalidArgumentException $exception) {
            return $this->renderWithErrors(['role_id' => ['Role khong hop le.']], $data);
        } catch (QueryException $exception) {
            return $this->renderWithErrors(['email' => ['Email da ton tai.']], $data);
        }

        return Response::redirect('/admin/users');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(array $errors, array $data): Response
    {
        $roles = $this->database->select(
            'SELECT id, name FROM roles WHERE tenant_id IS NULL OR tenant_id = ?',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.users.create', [
            'breadcrumb_items' => [['label' => 'Người dùng', 'url' => '/admin/users'], ['label' => 'Tạo mới']],
            'roles' => $roles,
            'errors' => $errors,
            'old' => [
                'name' => (string) ($data['name'] ?? ''),
                'email' => (string) ($data['email'] ?? ''),
                'role_id' => (string) ($data['role_id'] ?? ''),
            ],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }

    /**
     * Buoc 4 (Subscription/Goi dich vu, CMS-065): site khong gan goi hoac goi khong dat
     * max_users (NULL = khong gioi han) thi KHONG bi chan - zero-regression cho moi site hien co.
     * Bang "plans" co the chua ton tai o fixture test cu chua migrate them (cung nguyen tac
     * try/catch fallback da dung o Modules\Admin\DashboardController::fetchAnalyticsSummary()) -
     * bat Throwable, coi nhu khong gioi han thay vi lam vo request tao user.
     */
    private function exceedsUserQuota(int|string|null $siteId): bool
    {
        try {
            $row = $this->database->selectOne(
                'SELECT plans.max_users FROM sites LEFT JOIN plans ON plans.id = sites.plan_id WHERE sites.id = ?',
                [$siteId]
            );
        } catch (\Throwable) {
            return false;
        }

        if ($row === null || $row['max_users'] === null) {
            return false;
        }

        $currentUserCount = (int) ($this->database->selectOne(
            'SELECT COUNT(*) as c FROM users INNER JOIN user_site_roles ON user_site_roles.user_id = users.id WHERE user_site_roles.site_id = ?',
            [$siteId]
        )['c'] ?? 0);

        return $currentUserCount >= (int) $row['max_users'];
    }
}
