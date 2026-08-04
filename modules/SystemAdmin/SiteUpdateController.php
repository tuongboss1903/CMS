<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\PlatformAuditLogger;
use Core\SystemAdminAuth;
use Core\ThemeManager;
use Core\Validator;
use Core\View;

/**
 * POST /system-admin/sites/{id} - sua ten site + theme_active. Khong sua status o day (xem
 * Suspend/Activate). theme_active PHAI khop 1 key da discover() duoc qua ThemeManager - Buoc 1
 * truoc day nhan text tu do, co the lam site chay That gap ViewNotFoundException that
 * (View::resolvePath() fallback default theme nhung neu gia tri sai dinh dang van co the gay loi
 * khac) - Buoc 2 dong gap nay bang catalog that.
 */
final class SiteUpdateController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly PlatformAuditLogger $platformAuditLogger,
        private readonly ThemeManager $themeManager,
        private readonly Validator $validator,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $siteId = (int) $request->routeParam('id');
        $site = $this->database->selectOne('SELECT id FROM sites WHERE id = ?', [$siteId]);

        if ($site === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        /*
         * Validator::validate() chi coi 'nullable' la "bo qua" khi gia tri === null, khong phai
         * chuoi rong '' (select "-- Khong gan goi --" trong form that gui '', khong bao gio null) -
         * chuan hoa truoc de rule 'integer' khong bi fail sai khi Super Admin chu dong bo gan plan.
         */
        if (($data['plan_id'] ?? null) === '') {
            $data['plan_id'] = null;
        }

        $result = $this->validator->validate($data, [
            'name' => 'required|string',
            'theme_active' => 'nullable|string',
            'plan_id' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($siteId, $result->errors(), $data);
        }

        $themeActive = \array_key_exists('theme_active', $data) && $data['theme_active'] !== ''
            ? (string) $data['theme_active']
            : null;

        if ($themeActive !== null && $this->themeManager->find($themeActive) === null) {
            return $this->renderWithErrors($siteId, ['theme_active' => ['Theme khong hop le.']], $data);
        }

        $planId = \array_key_exists('plan_id', $data) && $data['plan_id'] !== null
            ? (int) $data['plan_id']
            : null;

        if ($planId !== null && $this->database->selectOne('SELECT id FROM plans WHERE id = ?', [$planId]) === null) {
            return $this->renderWithErrors($siteId, ['plan_id' => ['Goi dich vu khong hop le.']], $data);
        }

        $this->database->statement(
            'UPDATE sites SET name = ?, theme_active = ?, plan_id = ? WHERE id = ?',
            [(string) $data['name'], $themeActive, $planId, $siteId]
        );

        $this->platformAuditLogger->log(
            $request,
            'site.update',
            $siteId,
            'site',
            $siteId,
            newValues: ['name' => (string) $data['name'], 'theme_active' => $themeActive, 'plan_id' => $planId]
        );

        return Response::redirect('/system-admin/sites');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(int $siteId, array $errors, array $data): Response
    {
        $site = $this->database->selectOne('SELECT id, name, status, theme_active, plan_id FROM sites WHERE id = ?', [$siteId]);
        $domains = $this->database->select(
            'SELECT id, domain, is_primary FROM site_domains WHERE site_id = ? ORDER BY is_primary DESC, id ASC',
            [$siteId]
        );

        $html = $this->view->render('system_admin.pages.sites.edit', [
            'site' => $site,
            'domains' => $domains,
            'themes' => $this->themeManager->discover(),
            'plans' => $this->database->select('SELECT id, name, is_active FROM plans ORDER BY price_vnd ASC, id ASC'),
            'errors' => $errors,
            'old' => ['name' => (string) ($data['name'] ?? '')],
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
