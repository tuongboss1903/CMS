<?php

declare(strict_types=1);

namespace Plugins\Ecommerce;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\MiddlewareInterface;
use Core\PluginActivationService;
use Core\TenantManager;

/**
 * Phase 19 (Ecommerce MVP, CMS-056). Enforce bat/tat Plugin THEO TENANT o dung thoi diem tenant
 * DA duoc xac dinh (dispatch-time, sau TenantResolverMiddleware) - Application::boot() KHONG THE
 * loc theo tenant vi chay TRUOC khi tenant duoc resolve (xem ghi chu trong Application::boot()).
 * Toan bo route Admin + Public cua plugin nay (san pham/gio hang/dat hang) deu di qua middleware
 * nay - tenant chua kich hoat plugin se nhan 404, khong phan biet duoc voi route khong ton tai
 * (fail-closed, dung tinh than TenantResolverMiddleware da ap dung cho domain khong khop).
 */
final class EcommercePluginGuardMiddleware implements MiddlewareInterface
{
    private const PLUGIN_KEY = 'ecommerce';

    public function __construct(
        private readonly PluginActivationService $pluginActivation,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function process(Request $request, Closure $next): Response
    {
        if (!$this->tenantManager->check()) {
            return Response::html('404 Not Found', 404);
        }

        $tenantId = $this->tenantManager->id();

        if ($tenantId === null || !$this->pluginActivation->isActive($tenantId, self::PLUGIN_KEY)) {
            return Response::html('404 Not Found', 404);
        }

        return $next($request);
    }
}
