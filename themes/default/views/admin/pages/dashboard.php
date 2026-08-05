<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Bảng điều khiển</h1>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Trang đã xuất bản</div>
        <div class="stat-value" data-field="page_count"><?= $this->e((string) $page_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tổng số Media</div>
        <div class="stat-value" data-field="media_count"><?= $this->e((string) $media_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tổng số Người dùng</div>
        <div class="stat-value" data-field="user_count"><?= $this->e((string) $user_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tổng số Vai trò</div>
        <div class="stat-value" data-field="role_count"><?= $this->e((string) $role_count) ?></div>
    </div>
</div>

<div class="stat-grid" style="margin-top: var(--space-5);">
    <div class="stat-card">
        <div class="stat-label">Lượt xem (7 ngày)</div>
        <div class="stat-value" data-field="total_views"><?= $this->e((string) $total_views) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Khách truy cập độc nhất (7 ngày)</div>
        <div class="stat-value" data-field="unique_visitors"><?= $this->e((string) $unique_visitors) ?></div>
    </div>
</div>

<div class="card" style="margin-top: var(--space-5);">
    <h2 style="font-size:16px; margin-top:0;">Lượt xem 7 ngày gần đây</h2>
    <?php
    $maxViews = \max(1, \max(\array_column($daily_views, 'views')));
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
            ?>
        <rect x="<?= $this->e((string) $x) ?>" y="<?= $this->e((string) $y) ?>" width="<?= $this->e((string) $barWidth) ?>" height="<?= $this->e((string) $barHeight) ?>" fill="var(--color-primary, #2563eb)" rx="3"></rect>
        <text x="<?= $this->e((string) ($x + $barWidth / 2)) ?>" y="<?= $this->e((string) ($chartHeight + 14)) ?>" text-anchor="middle" font-size="10" fill="currentColor"><?= $this->e(\substr((string) $day['date'], 5)) ?></text>
        <?php endforeach; ?>
    </svg>
</div>

<div class="card" style="margin-top: var(--space-5);">
    <h2 style="font-size:16px; margin-top:0;">Trang xem nhiều nhất (7 ngày)</h2>
    <div class="table-wrap">
    <table class="data-table">
    <thead>
    <tr><th scope="col">Đường dẫn</th><th scope="col">Lượt xem</th></tr>
    </thead>
    <tbody>
    <?php foreach ($top_pages as $page): ?>
    <tr>
        <td><?= $this->e($page['path']) ?></td>
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

<div class="card" style="margin-top: var(--space-5);">
    <h2 style="font-size:16px; margin-top:0;">Thao tác nhanh</h2>
    <div class="flex gap-3" style="flex-wrap: wrap;">
        <a href="/admin/pages/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo trang mới</a>
        <a href="/admin/media" class="btn btn-secondary"><?php $this->include('admin.partials.icon', ['name' => 'upload']); ?> Tải Media lên</a>
        <a href="/admin/settings" class="btn btn-secondary"><?php $this->include('admin.partials.icon', ['name' => 'seo']); ?> Cấu hình SEO chung</a>
        <a href="/" class="btn btn-secondary" target="_blank" rel="noopener"><?php $this->include('admin.partials.icon', ['name' => 'search']); ?> Xem Public Site</a>
    </div>
</div>

<?php if (isset($recent_audit_logs) || isset($system_health)): ?>
<div class="dashboard-widget-grid" style="margin-top: var(--space-5);">
    <?php if (isset($recent_audit_logs)): ?>
    <div class="card">
        <h2 style="font-size:16px; margin-top:0;">Audit Log gần đây</h2>
        <div class="table-wrap">
        <table class="data-table">
        <thead><tr><th scope="col">Sự kiện</th><th scope="col">Thời gian</th></tr></thead>
        <tbody>
        <?php foreach ($recent_audit_logs as $log): ?>
        <tr>
            <td><span class="badge badge-neutral"><?= $this->e((string) $log['event']) ?></span></td>
            <td class="text-muted"><?= $this->e((string) $log['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent_audit_logs)): ?>
        <tr><td colspan="2" class="empty-state">Chưa có hoạt động nào.</td></tr>
        <?php endif; ?>
        </tbody>
        </table>
        </div>
        <p style="margin-top: var(--space-3); margin-bottom:0;"><a href="/admin/audit-logs">Xem toàn bộ Audit Log &rarr;</a></p>
    </div>
    <?php endif; ?>
    <?php if (isset($system_health)): ?>
    <div class="card">
        <h2 style="font-size:16px; margin-top:0;">Tình trạng hệ thống</h2>
        <?php foreach ($system_health as $item): ?>
        <div class="health-item">
            <span><span class="health-dot <?= ($item['ok'] ?? true) ? 'is-ok' : 'is-warn' ?>"></span><?= $this->e((string) $item['label']) ?></span>
            <span class="text-muted"><?= $this->e((string) $item['value']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-top: var(--space-5);">
    <h2 style="font-size:16px; margin-top:0;">Hoạt động gần đây</h2>
    <div class="table-wrap">
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
        <td><?= $this->e((string) $item['label']) ?></td>
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

<div class="card" style="margin-top: var(--space-5);">
    <form method="POST" action="/admin/logout">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
        <button type="submit" class="btn btn-secondary"><?php $this->include('admin.partials.icon', ['name' => 'logout']); ?> Đăng xuất</button>
    </form>
</div>
<?php $this->endSection(); ?>
