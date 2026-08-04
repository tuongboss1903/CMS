<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\View;

/** GET /system-admin/sites - danh sach toan bo site tren he thong (xuyen-tenant). */
final class SiteListController
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

        $status = \trim((string) ($request->query('status') ?? ''));

        $conditions = [];
        $bindings = [];

        if ($status !== '') {
            $conditions[] = 'status = ?';
            $bindings[] = $status;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . \implode(' AND ', $conditions);

        $sites = $this->database->select(
            "SELECT id, name, status, theme_active, created_at FROM sites{$where} ORDER BY id DESC",
            $bindings
        );

        $domains = $this->database->select('SELECT site_id, domain, is_primary FROM site_domains ORDER BY site_id, is_primary DESC');

        $domainsBySite = [];

        foreach ($domains as $domain) {
            $domainsBySite[(int) $domain['site_id']][] = $domain;
        }

        foreach ($sites as &$site) {
            $site['domains'] = $domainsBySite[(int) $site['id']] ?? [];
        }

        $html = $this->view->render('system_admin.pages.sites.list', [
            'sites' => $sites,
            'filters' => ['status' => $status],
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
