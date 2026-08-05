<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Bảng điều khiển hệ thống</h1>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:var(--space-4);margin-bottom:var(--space-5);">
    <div class="card">
        <div class="text-muted">Tổng số Site</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) $total_sites) ?></div>
    </div>
    <div class="card">
        <div class="text-muted">Site đang hoạt động</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) ($site_counts_by_status['active'] ?? 0)) ?></div>
    </div>
    <div class="card">
        <div class="text-muted">Site tạm khoá</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) ($site_counts_by_status['suspended'] ?? 0)) ?></div>
    </div>
    <div class="card">
        <div class="text-muted">Tổng Người dùng toàn hệ thống</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) $total_users) ?></div>
    </div>
    <div class="card">
        <div class="text-muted">Tổng Super Admin</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) $total_platform_admins) ?></div>
    </div>
</div>

<h2>Hoạt động gần đây (xuyên site)</h2>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Nguồn</th><th>Site</th><th>Sự kiện</th><th>Thời gian</th></tr></thead>
<tbody>
<?php foreach ($activity as $item): ?>
<tr>
    <td><span class="badge <?= $item['source'] === 'platform' ? 'badge-secondary' : 'badge-neutral' ?>"><?= $item['source'] === 'platform' ? 'Super Admin' : 'Site' ?></span></td>
    <td><?= $this->e((string) $item['label']) ?></td>
    <td><?= $this->e((string) $item['event']) ?></td>
    <td class="text-muted"><?= $this->e((string) ($item['event_at'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($activity)): ?>
<tr><td colspan="4" class="empty-state">Chưa có hoạt động nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
