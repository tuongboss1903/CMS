<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /admin/storage - Phase 24 (CMS-081). Man hinh Storage Usage cho tenant hien tai - lap gap
 * "storage_used_bytes"/"plans.max_storage_mb" da co san tu CMS-065/quota check cua
 * MediaUploadController nhung truoc gio chi hien 1 dong trong system_health cua Dashboard, chua co
 * trang rieng nao cho Admin xem chi tiet.
 *
 * Dung storage_used_bytes (counter da duy tri san, KHONG SUM(media.size) lai tu dau - tranh trung
 * nguon su that voi logic quota check da co) lam so lieu tong, nhung van doc rieng bang "media" de
 * hien breakdown theo loai file + top file lon nhat (2 du lieu nay KHONG co san o cot storage_used_bytes).
 *
 * Bang "plans" co the chua ton tai o fixture test cu (cung nguyen tac try/catch fallback da dung o
 * MediaUploadController::exceedsStorageQuota()/DashboardController::fetchSystemHealth()).
 */
final class StorageUsageController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('media.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $siteId = $this->tenantManager->id();
        $quota = $this->fetchQuota($siteId);
        $mediaRows = $this->database->select(
            'SELECT mime_type, file_name, size, created_at FROM media WHERE tenant_id = ? ORDER BY size DESC',
            [$siteId]
        );

        $html = $this->view->render('admin.pages.storage.usage', [
            'used_bytes' => $quota['used_bytes'],
            'limit_mb' => $quota['limit_mb'],
            'percent_used' => $quota['percent_used'],
            'breakdown' => $this->buildBreakdown($mediaRows),
            'largest_files' => \array_slice($mediaRows, 0, 10),
            'breadcrumb_items' => [['label' => 'Dung lượng lưu trữ']],
        ]);

        return Response::html($html);
    }

    /** @return array{used_bytes: int, limit_mb: int|null, percent_used: float|null} */
    private function fetchQuota(int|string|null $siteId): array
    {
        try {
            $row = $this->database->selectOne(
                'SELECT sites.storage_used_bytes, plans.max_storage_mb
                    FROM sites LEFT JOIN plans ON plans.id = sites.plan_id
                    WHERE sites.id = ?',
                [$siteId]
            );
        } catch (\Throwable) {
            $row = null;
        }

        $usedBytes = (int) ($row['storage_used_bytes'] ?? 0);
        $limitMb = isset($row['max_storage_mb']) ? (int) $row['max_storage_mb'] : null;
        $percentUsed = $limitMb !== null && $limitMb > 0
            ? \min(100.0, ($usedBytes / ($limitMb * 1024 * 1024)) * 100)
            : null;

        return ['used_bytes' => $usedBytes, 'limit_mb' => $limitMb, 'percent_used' => $percentUsed];
    }

    /**
     * @param list<array<string, mixed>> $mediaRows
     * @return list<array{category: string, label: string, count: int, size_bytes: int}>
     */
    private function buildBreakdown(array $mediaRows): array
    {
        $labels = ['image' => 'Hình ảnh', 'video' => 'Video', 'application' => 'Tài liệu', 'other' => 'Khác'];
        $totals = ['image' => 0, 'video' => 0, 'application' => 0, 'other' => 0];
        $counts = ['image' => 0, 'video' => 0, 'application' => 0, 'other' => 0];

        foreach ($mediaRows as $row) {
            $prefix = \explode('/', (string) $row['mime_type'])[0];
            $category = isset($labels[$prefix]) ? $prefix : 'other';
            $totals[$category] += (int) $row['size'];
            $counts[$category]++;
        }

        $result = [];

        foreach ($labels as $category => $label) {
            $result[] = ['category' => $category, 'label' => $label, 'count' => $counts[$category], 'size_bytes' => $totals[$category]];
        }

        return $result;
    }
}
