<?php

declare(strict_types=1);

namespace Tests\Fixtures\App\plugins\TestPlugin;

use Core\Http\Request;
use Core\Http\Response;
use Core\Session;
use Core\TenantManager;

/**
 * Fixture Controller - resolve qua Container that (giong Plugins\Ecommerce\Controllers\*) de xac
 * nhan Route dang ky qua Hook "plugin.routes.register" CO nhan dung StartSessionMiddleware/
 * TenantResolverMiddleware (regression test cho loi da vá o Application::boot(), xem
 * ApplicationTest::testPluginRouteHasSessionAndTenantResolved()).
 */
final class PluginPingController
{
    public function __construct(
        private readonly Session $session,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        return Response::json([
            'session_started' => $this->session->isStarted(),
            'tenant_id' => $this->tenantManager->id(),
        ]);
    }
}
