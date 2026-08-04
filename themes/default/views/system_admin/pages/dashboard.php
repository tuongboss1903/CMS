<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Dashboard he thong</h1>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:var(--space-4);margin-bottom:var(--space-5);">
    <div class="card">
        <div class="text-muted">Tong so Site</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) $total_sites) ?></div>
    </div>
    <div class="card">
        <div class="text-muted">Site dang Active</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) ($site_counts_by_status['active'] ?? 0)) ?></div>
    </div>
    <div class="card">
        <div class="text-muted">Site Suspended</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) ($site_counts_by_status['suspended'] ?? 0)) ?></div>
    </div>
    <div class="card">
        <div class="text-muted">Tong User toan he thong</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) $total_users) ?></div>
    </div>
    <div class="card">
        <div class="text-muted">Tong Super Admin</div>
        <div style="font-size:2rem;font-weight:700;"><?= $this->e((string) $total_platform_admins) ?></div>
    </div>
</div>

<h2>Hoat dong gan day (xuyen site)</h2>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Nguon</th><th>Site</th><th>Su kien</th><th>Thoi gian</th></tr></thead>
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
<tr><td colspan="4" class="empty-state">Chua co hoat dong nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
