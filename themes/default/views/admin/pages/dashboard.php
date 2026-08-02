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
