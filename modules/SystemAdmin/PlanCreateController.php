<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\PlatformAuditLogger;
use Core\SystemAdminAuth;
use Core\Validator;
use Core\View;

/** POST /system-admin/plans - tao goi dich vu moi. max_users/max_storage_mb/max_products bo trong = khong gioi han. */
final class PlanCreateController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly PlatformAuditLogger $platformAuditLogger,
        private readonly Validator $validator,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $data = $this->normalizeEmptyNumericFields($request->all());

        $result = $this->validator->validate($data, [
            'key' => 'required|string',
            'name' => 'required|string',
            'price_vnd' => 'nullable|integer',
            'billing_cycle' => 'nullable|string',
            'max_users' => 'nullable|integer',
            'max_storage_mb' => 'nullable|integer',
            'max_products' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($result->errors(), $data);
        }

        $key = (string) $data['key'];
        $name = (string) $data['name'];
        $priceVnd = $this->intOrDefault($data, 'price_vnd', 0);
        $billingCycle = \trim((string) ($data['billing_cycle'] ?? '')) !== '' ? (string) $data['billing_cycle'] : 'monthly';
        $maxUsers = $this->intOrNull($data, 'max_users');
        $maxStorageMb = $this->intOrNull($data, 'max_storage_mb');
        $maxProducts = $this->intOrNull($data, 'max_products');

        try {
            $this->database->insert(
                'INSERT INTO plans (`key`, name, price_vnd, billing_cycle, max_users, max_storage_mb, max_products, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
                [$key, $name, $priceVnd, $billingCycle, $maxUsers, $maxStorageMb, $maxProducts]
            );
        } catch (QueryException $exception) {
            return $this->renderWithErrors(['key' => ['Key da duoc su dung.']], $data);
        }

        $planId = (int) $this->database->connection()->lastInsertId();
        $this->platformAuditLogger->log($request, 'plan.create', null, 'plan', $planId, newValues: ['key' => $key, 'name' => $name]);

        return Response::redirect('/system-admin/plans');
    }

    /**
     * Validator::validate() chi coi 'nullable' la "bo qua" khi gia tri === null, khong phai chuoi
     * rong '' (input HTML form de trong luon gui '' , khong bao gio null) - chuan hoa truoc de
     * rule 'integer' khong bi fail sai tren field thuc su duoc phep bo trong.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeEmptyNumericFields(array $data): array
    {
        foreach (['price_vnd', 'max_users', 'max_storage_mb', 'max_products'] as $field) {
            if (($data[$field] ?? null) === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function intOrDefault(array $data, string $field, int $default): int
    {
        return \array_key_exists($field, $data) && $data[$field] !== null ? (int) $data[$field] : $default;
    }

    /** @param array<string, mixed> $data */
    private function intOrNull(array $data, string $field): ?int
    {
        return \array_key_exists($field, $data) && $data[$field] !== null ? (int) $data[$field] : null;
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(array $errors, array $data): Response
    {
        $html = $this->view->render('system_admin.pages.plans.create', [
            'errors' => $errors,
            'old' => $data,
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
