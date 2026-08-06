<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Chi bao xu huong nho cho KPI Card (Design Audit Phase 8) - CHI dung cho so lieu THEO KY that
 * su co du lieu ky truoc (total_views/unique_visitors), khong dung cho tong tich luy (page/media/
 * user/role count). Closure (khong phai function toan cuc) - view co the duoc render nhieu lan
 * trong cung 1 process (vd PHPUnit) nen "function" top-level se fatal "Cannot redeclare".
 *
 * @param array{direction: string, percent: int|null} $trend
 */
$renderStatTrend = function (array $trend) {
    $arrow = match ($trend['direction']) {
        'up' => '&#9650;',
        'down' => '&#9660;',
        default => '&#8213;',
    };
    $label = $trend['percent'] !== null
        ? \abs($trend['percent']) . '% so với kỳ trước'
        : 'so với kỳ trước';

    return '<div class="stat-trend stat-trend--' . $this->e($trend['direction']) . '">'
        . $arrow . ' ' . $this->e($label) . '</div>';
};
?>
<h1>Bảng điều khiển</h1>

<div class="stack-5">
<div class="stat-grid">
    <div class="stat-card">
        <div class="kpi-top-row">
            <div class="stat-label">Trang đã xuất bản</div>
            <?php $this->include('admin.partials.icon', ['name' => 'kpi-pages', 'class' => 'icon icon--kpi']); ?>
        </div>
        <div class="stat-value" data-field="page_count"><?= $this->e((string) $page_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="kpi-top-row">
            <div class="stat-label">Tổng số Media</div>
            <?php $this->include('admin.partials.icon', ['name' => 'kpi-media', 'class' => 'icon icon--kpi']); ?>
        </div>
        <div class="stat-value" data-field="media_count"><?= $this->e((string) $media_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="kpi-top-row">
            <div class="stat-label">Tổng số Người dùng</div>
            <?php $this->include('admin.partials.icon', ['name' => 'kpi-users', 'class' => 'icon icon--kpi']); ?>
        </div>
        <div class="stat-value" data-field="user_count"><?= $this->e((string) $user_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="kpi-top-row">
            <div class="stat-label">Tổng số Vai trò</div>
            <?php $this->include('admin.partials.icon', ['name' => 'kpi-roles', 'class' => 'icon icon--kpi']); ?>
        </div>
        <div class="stat-value" data-field="role_count"><?= $this->e((string) $role_count) ?></div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card stat-card--primary">
        <div class="kpi-top-row">
            <div class="stat-label">Lượt xem (7 ngày)</div>
            <?php $this->include('admin.partials.icon', ['name' => 'star', 'class' => 'icon icon--kpi']); ?>
        </div>
        <div class="stat-value" data-field="total_views"><?= $this->e((string) $total_views) ?></div>
        <?= $this->raw($renderStatTrend($total_views_trend)) ?>
    </div>
    <div class="stat-card">
        <div class="stat-label">Khách truy cập độc nhất (7 ngày)</div>
        <div class="stat-value" data-field="unique_visitors"><?= $this->e((string) $unique_visitors) ?></div>
        <?= $this->raw($renderStatTrend($unique_visitors_trend)) ?>
    </div>
</div>

<!-- Luoi 2 cot theo ty le (Design Audit Phase 8) - keo "Tinh trang he thong" len ngang hang bieu
     do (widget liec mat roi thoi, khong can doc ky) thay vi nam cuoi trang. -->
<div class="dashboard-row-8-4">
    <div class="card">
        <h2 style="font-size:16px;">Lượt xem 7 ngày gần đây</h2>
        <?php
        $maxViews = \max(1, \max(\array_column($daily_views, 'views')));
$peakIndex = \array_search($maxViews, \array_column($daily_views, 'views'), true);
$barWidth = 40;
$gap = 16;
$chartHeight = 120;
$chartWidth = \count($daily_views) * ($barWidth + $gap);
?>
        <svg viewBox="0 0 <?= $this->e((string) $chartWidth) ?> <?= $this->e((string) ($chartHeight + 20)) ?>" width="100%" height="140" role="img" aria-label="Biểu đồ lượt xem 7 ngày gần đây">
            <?php foreach ($daily_views as $index => $day): ?>
            <?php
    $barHeight = (int) \round(($day['views'] / $maxViews) * $chartHeight);
                $x = $index * ($barWidth + $gap);
                $y = $chartHeight - $barHeight;
                $barColor = $index === $peakIndex ? 'var(--color-accent)' : 'var(--color-accent-secondary)';
                ?>
            <rect x="<?= $this->e((string) $x) ?>" y="<?= $this->e((string) $y) ?>" width="<?= $this->e((string) $barWidth) ?>" height="<?= $this->e((string) $barHeight) ?>" fill="<?= $this->e($barColor) ?>" rx="3"></rect>
            <text x="<?= $this->e((string) ($x + $barWidth / 2)) ?>" y="<?= $this->e((string) ($chartHeight + 14)) ?>" text-anchor="middle" font-size="10" fill="currentColor"><?= $this->e(\substr((string) $day['date'], 5)) ?></text>
            <?php endforeach; ?>
        </svg>
    </div>
    <?php if (isset($system_health)): ?>
    <div class="card">
        <h2 style="font-size:16px;">Tình trạng hệ thống</h2>
        <?php foreach ($system_health as $item): ?>
        <div class="health-item">
            <span><span class="health-dot <?= ($item['ok'] ?? true) ? 'is-ok' : 'is-warn' ?>"></span><?= $this->e((string) $item['label']) ?></span>
            <span class="text-muted"><?= $this->e((string) $item['value']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Nhom "gan giong nhau" canh nhau (Design Audit Phase 8) - Trang xem nhieu + Audit Log deu la
     danh sach ngan, xem-roi-thoi, thay vi xep doc lien tiep cach xa nhau. -->
<div class="dashboard-row-6-6">
    <div class="card dashboard-list-card">
        <h2 style="font-size:16px;">Trang xem nhiều nhất (7 ngày)</h2>
        <div class="table-wrap table-wrap--flat">
        <table class="data-table">
        <thead>
        <tr><th scope="col">Đường dẫn</th><th scope="col">Lượt xem</th></tr>
        </thead>
        <tbody>
        <?php foreach ($top_pages as $page): ?>
        <tr>
            <td class="truncate-cell"><span class="text-truncate" title="<?= $this->e($page['path']) ?>"><?= $this->e($page['path']) ?></span></td>
            <td><?= $this->e((string) $page['views']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($top_pages)): ?>
        <tr><td colspan="2" class="empty-state">Chưa có dữ liệu.</td></tr>
        <?php endif; ?>
        </tbody>
        </table>
        </div>
    </div>
    <?php if (isset($recent_audit_logs)): ?>
    <div class="card dashboard-list-card">
        <h2 style="font-size:16px;">Audit Log gần đây</h2>
        <div class="table-wrap table-wrap--flat">
        <table class="data-table">
        <thead><tr><th scope="col">Sự kiện</th><th scope="col">Thời gian</th></tr></thead>
        <tbody>
        <?php foreach ($recent_audit_logs as $log): ?>
        <?php $eventCode = (string) $log['event']; ?>
        <tr>
            <td class="truncate-cell">
                <span class="badge <?= $this->e(\Modules\Admin\AuditLogPresenter::eventBadgeClass($eventCode)) ?>" title="<?= $this->e($eventCode) ?>">
                    <?= $this->e(\Modules\Admin\AuditLogPresenter::eventLabel($eventCode)) ?>
                </span>
            </td>
            <td class="text-muted"><?= $this->e((string) $log['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent_audit_logs)): ?>
        <tr><td colspan="2" class="empty-state">Chưa có hoạt động nào.</td></tr>
        <?php endif; ?>
        </tbody>
        </table>
        </div>
        <a href="/admin/audit-logs" class="card-footer-link">Xem toàn bộ Audit Log <span aria-hidden="true">&rarr;</span></a>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2 style="font-size:16px;">Thao tác nhanh</h2>
    <div class="flex gap-3" style="flex-wrap: wrap;">
        <a href="/admin/pages/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo trang mới</a>
        <a href="/admin/media" class="btn btn-secondary"><?php $this->include('admin.partials.icon', ['name' => 'upload']); ?> Tải Media lên</a>
        <a href="/admin/settings" class="btn btn-secondary"><?php $this->include('admin.partials.icon', ['name' => 'seo']); ?> Cấu hình SEO chung</a>
        <a href="/" class="btn btn-secondary" target="_blank" rel="noopener"><?php $this->include('admin.partials.icon', ['name' => 'search']); ?> Xem Public Site</a>
    </div>
</div>

<div class="card">
    <h2 style="font-size:16px;">Hoạt động gần đây</h2>
    <div class="table-wrap table-wrap--flat">
    <table class="data-table">
    <thead>
    <tr><th scope="col">Loại</th><th scope="col">Nội dung</th><th scope="col">Thời gian</th></tr>
    </thead>
    <tbody>
    <?php foreach ($activity as $item): ?>
    <tr>
        <td>
        <?php if ($item['type'] === 'page'): ?>
            <span class="badge badge-neutral">Trang</span>
        <?php elseif ($item['type'] === 'media'): ?>
            <span class="badge badge-warning">Media</span>
        <?php else: ?>
            <span class="badge badge-success">Người dùng</span>
        <?php endif; ?>
        </td>
        <td class="truncate-cell"><span class="text-truncate" title="<?= $this->e((string) $item['label']) ?>"><?= $this->e((string) $item['label']) ?></span></td>
        <td class="text-muted"><?= $this->e((string) ($item['event_at'] ?? '')) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($activity)): ?>
    <tr><td colspan="3" class="empty-state">Chưa có hoạt động nào.</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
    </div>
</div>
</div>
<?php $this->endSection(); ?>
