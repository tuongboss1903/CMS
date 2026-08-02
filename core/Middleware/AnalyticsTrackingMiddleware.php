<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use Modules\Analytics\AnalyticsService;
use Throwable;

/**
 * Phase 12 (Advanced Analytics Dashboard, CMS-049). Gan truc tiep vao tung route public trong
 * modules/Public/routes.php (khong co "routes/web.php" trong du an - route dang ky theo tung
 * module, nap qua ModuleManager). "After" middleware - ghi log SAU KHI co Response, chi ghi khi
 * status 200 (khong ghi 404/loi - tranh sai lech chi so).
 *
 * Silent-fail tuyet doi: loi ghi DB (vd mat ket noi, bang chua ton tai o moi truong dang bao tri)
 * KHONG DUOC lam gian doan trai nghiem cong khai - nuot toan bo Throwable, tra nguyen Response
 * that (khong doi status/body).
 */
final class AnalyticsTrackingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AnalyticsService $analyticsService)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            try {
                $this->analyticsService->track($request);
            } catch (Throwable) {
                // Silent fail co chu dich - xem docblock class.
            }
        }

        return $response;
    }
}
