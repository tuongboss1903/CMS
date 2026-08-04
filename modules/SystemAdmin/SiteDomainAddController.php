<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\ThemeManager;
use Core\Validator;
use Core\View;

/** POST /system-admin/sites/{id}/domains - them domain phu (khong phai domain chinh) cho 1 site. */
final class SiteDomainAddController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
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
        $site = $this->database->selectOne('SELECT id, name, status, theme_active FROM sites WHERE id = ?', [$siteId]);

        if ($site === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();
        $result = $this->validator->validate($data, ['domain' => 'required|string']);

        if ($result->fails()) {
            return $this->renderWithErrors($site, $result->errors());
        }

        $domain = \strtolower(\trim((string) $data['domain']));

        try {
            $this->database->insert(
                'INSERT INTO site_domains (site_id, domain, is_primary) VALUES (?, ?, 0)',
                [$siteId, $domain]
            );
        } catch (QueryException $exception) {
            return $this->renderWithErrors($site, ['domain' => ['Domain da duoc su dung.']]);
        }

        return Response::redirect("/system-admin/sites/{$siteId}/edit");
    }

    /**
     * @param array<string, mixed> $site
     * @param array<string, list<string>> $errors
     */
    private function renderWithErrors(array $site, array $errors): Response
    {
        $siteId = (int) $site['id'];
        $domains = $this->database->select(
            'SELECT id, domain, is_primary FROM site_domains WHERE site_id = ? ORDER BY is_primary DESC, id ASC',
            [$siteId]
        );

        $html = $this->view->render('system_admin.pages.sites.edit', [
            'site' => $site,
            'domains' => $domains,
            'themes' => $this->themeManager->discover(),
            'errors' => $errors,
            'old' => ['name' => (string) $site['name']],
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
