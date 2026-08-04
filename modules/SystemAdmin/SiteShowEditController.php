<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\View;

/** GET /system-admin/sites/{id}/edit - form sua site + quan ly domain phu. */
final class SiteShowEditController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
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

        $domains = $this->database->select(
            'SELECT id, domain, is_primary FROM site_domains WHERE site_id = ? ORDER BY is_primary DESC, id ASC',
            [$siteId]
        );

        $html = $this->view->render('system_admin.pages.sites.edit', [
            'site' => $site,
            'domains' => $domains,
            'errors' => [],
            'old' => ['name' => (string) $site['name']],
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
