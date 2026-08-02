<?php

declare(strict_types=1);

namespace Modules\Analytics;

use Core\Config;
use Core\Database;
use Core\Http\Request;
use Core\TenantManager;

/**
 * Phase 12 (Advanced Analytics Dashboard, CMS-049). Service Layer - cung mo hinh voi
 * Modules\Settings\SiteSettingsManager (Owner Decision Phase 4): duoc dung tu >=2 noi doc lap
 * (AnalyticsTrackingMiddleware ghi, Admin\DashboardController doc), khong phai 1 Controller so
 * huu logic. Khong tu tao module.json/routes.php - class nay khong co HTTP endpoint rieng, chi
 * autoload qua PSR-4 + Container auto-wiring (giong SiteSettingsManager).
 *
 * Moi cau truy van doc PHAI loc tenant_id = ? (Multi-tenancy Isolation) - khong co ngoai le.
 * IP chi luu duoi dang hash (khong luu IP tho) - anonymize nhe bang sha256 + app.key lam salt,
 * khong phai crypto-secure (du cho muc dich thong ke, khong phai bao mat).
 */
final class AnalyticsService
{
    private const VALID_PERIODS = ['24h', '7d', '30d'];

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    /**
     * Ghi 1 luot xem cho tenant hien tai. Khong tu catch Throwable o day - AnalyticsTrackingMiddleware
     * chiu trach nhiem silent-fail (Service chi lo dung du lieu, khong lo resilience cua HTTP layer).
     */
    public function track(Request $request): void
    {
        $tenantId = $this->tenantManager->id();
        $path = $request->path();
        $pageId = $this->resolvePageId($tenantId, $path);

        $this->database->insert(
            'INSERT INTO analytics_views (tenant_id, page_id, path, ip_hash, user_agent, referrer)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $tenantId,
                $pageId,
                $path,
                $this->hashIp($request->ip()),
                $request->userAgent(),
                $request->header('Referer'),
            ]
        );
    }

    public function totalViews(string $period = '7d'): int
    {
        $row = $this->database->selectOne(
            'SELECT COUNT(*) as count FROM analytics_views WHERE tenant_id = ? AND created_at >= ?',
            [$this->tenantManager->id(), $this->cutoff($period)]
        );

        return (int) ($row['count'] ?? 0);
    }

    public function uniqueVisitors(string $period = '7d'): int
    {
        $row = $this->database->selectOne(
            'SELECT COUNT(DISTINCT ip_hash) as count FROM analytics_views WHERE tenant_id = ? AND created_at >= ?',
            [$this->tenantManager->id(), $this->cutoff($period)]
        );

        return (int) ($row['count'] ?? 0);
    }

    /** @return list<array{path: string, views: int}> */
    public function topPages(string $period = '7d', int $limit = 5): array
    {
        $rows = $this->database->select(
            'SELECT path, COUNT(*) as views FROM analytics_views
             WHERE tenant_id = ? AND created_at >= ?
             GROUP BY path ORDER BY views DESC LIMIT ' . \max(1, $limit),
            [$this->tenantManager->id(), $this->cutoff($period)]
        );

        return \array_map(
            static fn (array $row): array => ['path' => (string) $row['path'], 'views' => (int) $row['views']],
            $rows
        );
    }

    /** @return list<array{referrer: string, views: int}> */
    public function topReferrers(string $period = '7d', int $limit = 5): array
    {
        $rows = $this->database->select(
            "SELECT COALESCE(NULLIF(referrer, ''), 'Direct') as referrer, COUNT(*) as views
             FROM analytics_views
             WHERE tenant_id = ? AND created_at >= ?
             GROUP BY referrer ORDER BY views DESC LIMIT " . \max(1, $limit),
            [$this->tenantManager->id(), $this->cutoff($period)]
        );

        return \array_map(
            static fn (array $row): array => ['referrer' => (string) $row['referrer'], 'views' => (int) $row['views']],
            $rows
        );
    }

    /**
     * Dung cho ve bieu do SVG thuan tren Dashboard - luon tra du $days phan tu (0 view -> 0),
     * khong bo trong ngay khong co du lieu (chart can truc X lien tuc).
     *
     * @return list<array{date: string, views: int}>
     */
    public function dailyViews(int $days = 7): array
    {
        $tenantId = $this->tenantManager->id();
        $cutoff = \date('Y-m-d H:i:s', \time() - $days * 86400);

        $rows = $this->database->select(
            "SELECT SUBSTR(created_at, 1, 10) as day, COUNT(*) as views
             FROM analytics_views
             WHERE tenant_id = ? AND created_at >= ?
             GROUP BY day",
            [$tenantId, $cutoff]
        );

        $byDay = [];

        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (int) $row['views'];
        }

        $result = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = \date('Y-m-d', \time() - $i * 86400);
            $result[] = ['date' => $date, 'views' => $byDay[$date] ?? 0];
        }

        return $result;
    }

    private function resolvePageId(int|string|null $tenantId, string $path): ?int
    {
        $page = $path === '/'
            ? $this->database->selectOne(
                'SELECT id FROM pages WHERE tenant_id = ? AND is_homepage = 1 AND status = ? AND deleted_at IS NULL',
                [$tenantId, 'published']
            )
            : $this->database->selectOne(
                'SELECT id FROM pages WHERE tenant_id = ? AND slug = ? AND status = ? AND deleted_at IS NULL',
                [$tenantId, \ltrim($path, '/'), 'published']
            );

        return $page !== null ? (int) $page['id'] : null;
    }

    private function hashIp(string $ip): string
    {
        return \hash('sha256', $ip . (string) $this->config->get('app.key', ''));
    }

    private function cutoff(string $period): string
    {
        $seconds = match (\in_array($period, self::VALID_PERIODS, true) ? $period : '7d') {
            '24h' => 86400,
            '30d' => 30 * 86400,
            default => 7 * 86400,
        };

        return \date('Y-m-d H:i:s', \time() - $seconds);
    }
}
