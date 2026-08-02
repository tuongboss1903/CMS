<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Dashboard</h1>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Trang da xuat ban</div>
        <div class="stat-value" data-field="page_count"><?= $this->e((string) $page_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tong so Media</div>
        <div class="stat-value" data-field="media_count"><?= $this->e((string) $media_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tong so User</div>
        <div class="stat-value" data-field="user_count"><?= $this->e((string) $user_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tong so Role</div>
        <div class="stat-value" data-field="role_count"><?= $this->e((string) $role_count) ?></div>
    </div>
</div>

<div class="stat-grid" style="margin-top: var(--space-5);">
    <div class="stat-card">
        <div class="stat-label">Luot xem (7 ngay)</div>
        <div class="stat-value" data-field="total_views"><?= $this->e((string) $total_views) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Khach truy cap doc nhat (7 ngay)</div>
        <div class="stat-value" data-field="unique_visitors"><?= $this->e((string) $unique_visitors) ?></div>
    </div>
</div>

<div class="card" style="margin-top: var(--space-5);">
    <h2 style="font-size:16px; margin-top:0;">Luot xem 7 ngay gan day</h2>
    <?php
    $maxViews = \max(1, \max(\array_column($daily_views, 'views')));
    $barWidth = 40;
    $gap = 16;
    $chartHeight = 120;
    $chartWidth = \count($daily_views) * ($barWidth + $gap);
    ?>
    <svg viewBox="0 0 <?= $this->e((string) $chartWidth) ?> <?= $this->e((string) ($chartHeight + 20)) ?>" width="100%" height="140" role="img" aria-label="Bieu do luot xem 7 ngay gan day">
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
    <h2 style="font-size:16px; margin-top:0;">Trang xem nhieu nhat (7 ngay)</h2>
    <div class="table-wrap">
    <table class="data-table">
    <thead>
    <tr><th>Duong dan</th><th>Luot xem</th></tr>
    </thead>
    <tbody>
    <?php foreach ($top_pages as $page): ?>
    <tr>
        <td><?= $this->e($page['path']) ?></td>
        <td><?= $this->e((string) $page['views']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($top_pages)): ?>
    <tr><td colspan="2" class="empty-state">Chua co du lieu.</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
    </div>
</div>

<div class="card" style="margin-top: var(--space-5);">
    <h2 style="font-size:16px; margin-top:0;">Thao tac nhanh</h2>
    <div class="flex gap-3" style="flex-wrap: wrap;">
        <a href="/admin/pages/create" class="btn btn-primary">+ Tao trang moi</a>
        <a href="/admin/media" class="btn btn-secondary">Tai Media len</a>
        <a href="/admin/settings" class="btn btn-secondary">Cau hinh SEO chung</a>
        <a href="/" class="btn btn-secondary" target="_blank" rel="noopener">Xem Public Site</a>
    </div>
</div>

<div class="card" style="margin-top: var(--space-5);">
    <h2 style="font-size:16px; margin-top:0;">Hoat dong gan day</h2>
    <div class="table-wrap">
    <table class="data-table">
    <thead>
    <tr><th>Loai</th><th>Noi dung</th><th>Thoi gian</th></tr>
    </thead>
    <tbody>
    <?php foreach ($activity as $item): ?>
    <tr>
        <td>
        <?php if ($item['type'] === 'page'): ?>
            <span class="badge badge-neutral">Page</span>
        <?php elseif ($item['type'] === 'media'): ?>
            <span class="badge badge-warning">Media</span>
        <?php else: ?>
            <span class="badge badge-success">User</span>
        <?php endif; ?>
        </td>
        <td><?= $this->e((string) $item['label']) ?></td>
        <td class="text-muted"><?= $this->e((string) ($item['event_at'] ?? '')) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($activity)): ?>
    <tr><td colspan="3" class="empty-state">Chua co hoat dong nao.</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
    </div>
</div>

<div class="card" style="margin-top: var(--space-5);">
    <form method="POST" action="/admin/logout">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
        <button type="submit" class="btn btn-secondary">Dang xuat</button>
    </form>
</div>
<?php $this->endSection(); ?>
