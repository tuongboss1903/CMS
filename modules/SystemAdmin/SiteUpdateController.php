<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\Validator;
use Core\View;

/** POST /system-admin/sites/{id} - sua ten site + theme_active. Khong sua status o day (xem Suspend/Activate). */
final class SiteUpdateController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
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

        $result = $this->validator->validate($data, [
            'name' => 'required|string',
            'theme_active' => 'nullable|string',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($siteId, $result->errors(), $data);
        }

        $themeActive = \array_key_exists('theme_active', $data) && $data['theme_active'] !== ''
            ? (string) $data['theme_active']
            : null;

        $this->database->statement(
            'UPDATE sites SET name = ?, theme_active = ? WHERE id = ?',
            [(string) $data['name'], $themeActive, $siteId]
        );

        return Response::redirect('/system-admin/sites');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(int $siteId, array $errors, array $data): Response
    {
        $site = $this->database->selectOne('SELECT id, name, status, theme_active FROM sites WHERE id = ?', [$siteId]);
        $domains = $this->database->select(
            'SELECT id, domain, is_primary FROM site_domains WHERE site_id = ? ORDER BY is_primary DESC, id ASC',
            [$siteId]
        );

        $html = $this->view->render('system_admin.pages.sites.edit', [
            'site' => $site,
            'domains' => $domains,
            'errors' => $errors,
            'old' => ['name' => (string) ($data['name'] ?? '')],
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
