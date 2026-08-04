<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\PlatformAuditLogger;
use Core\SystemAdminAuth;

/** POST /system-admin/site-domains/{id}/delete - khong cho xoa domain chinh (is_primary), tranh site mat domain truy cap duoc. */
final class SiteDomainDeleteController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Database $database,
        private readonly PlatformAuditLogger $platformAuditLogger,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $domainId = (int) $request->routeParam('id');
        $domain = $this->database->selectOne('SELECT id, site_id, domain, is_primary FROM site_domains WHERE id = ?', [$domainId]);

        if ($domain === null) {
            return Response::html('404 Not Found', 404);
        }

        if ((bool) $domain['is_primary']) {
            return Response::html('403 Forbidden', 403);
        }

        $this->database->delete('DELETE FROM site_domains WHERE id = ?', [$domainId]);
        $this->platformAuditLogger->log(
            $request,
            'site.domain_delete',
            (int) $domain['site_id'],
            'site',
            (int) $domain['site_id'],
            oldValues: ['domain' => $domain['domain']]
        );

        return Response::redirect("/system-admin/sites/{$domain['site_id']}/edit");
    }
}
