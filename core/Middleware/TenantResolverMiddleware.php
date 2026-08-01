<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * Resolve domain (Request::getHost()) -> tenant qua site_domains/sites, goi TenantManager::setCurrent().
 * Host header CHI dung lam gia tri lookup qua prepared statement, khong tin cay cho muc dich khac.
 * Domain khong khop -> 404 ngay (fail-closed), KHONG fallback tenant mac dinh. Site status !== 'active'
 * (maintenance/suspended/NULL/gia tri la) cung fail-closed - chan ngay, KHONG setCurrent(), KHONG
 * goi $next(). Khong xu ly Auth/Permission/Super Admin - thuoc pham vi CMS rieng sau nay.
 */
final class TenantResolverMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function process(Request $request, Closure $next): Response
    {
        $site = $this->database->selectOne(
            'SELECT sites.* FROM sites
             INNER JOIN site_domains ON site_domains.site_id = sites.id
             WHERE site_domains.domain = ?',
            [$request->getHost()]
        );

        if ($site === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        if ($site['status'] !== 'active') {
            return $this->statusBlockedResponse((string) $site['status']);
        }

        $this->tenantManager->setCurrent((int) $site['id'], $site);

        return $next($request);
    }

    private function statusBlockedResponse(string $status): Response
    {
        [$httpStatus, $message] = match ($status) {
            'maintenance' => [503, 'Site is under maintenance.'],
            'suspended' => [403, 'Site has been suspended.'],
            default => [403, 'Site is not available.'],
        };

        return Response::json([
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => [],
        ], $httpStatus);
    }
}
