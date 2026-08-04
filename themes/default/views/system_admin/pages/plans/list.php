<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Goi dich vu</h1>
    <a href="/system-admin/plans/create" class="btn btn-primary">+ Tao goi moi</a>
</div>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Key</th><th>Ten</th><th>Gia (VND)</th><th>Chu ky</th><th>Gioi han</th><th>So Site</th><th>Trang thai</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($plans as $plan): ?>
<tr>
    <td><code><?= $this->e((string) $plan['key']) ?></code></td>
    <td><?= $this->e((string) $plan['name']) ?></td>
    <td><?= $this->e(\number_format((float) $plan['price_vnd'], 0, ',', '.')) ?></td>
    <td><?= $this->e((string) $plan['billing_cycle']) ?></td>
    <td class="text-muted">
        User: <?= $this->e((string) ($plan['max_users'] ?? 'Khong gioi han')) ?>,
        Storage: <?= $this->e((string) ($plan['max_storage_mb'] ?? 'Khong gioi han')) ?> MB,
        Product: <?= $this->e((string) ($plan['max_products'] ?? 'Khong gioi han')) ?>
    </td>
    <td><?= $this->e((string) $plan['site_count']) ?></td>
    <td data-field="status">
        <span class="badge <?= $plan['is_active'] ? 'badge-success' : 'badge-secondary' ?>"><?= $plan['is_active'] ? 'Dang ban' : 'An' ?></span>
    </td>
    <td>
        <div class="table-actions">
        <a href="/system-admin/plans/<?= $this->e((string) $plan['id']) ?>/edit" class="btn btn-secondary btn-sm">Sua</a>
        <form method="POST" action="/system-admin/plans/<?= $this->e((string) $plan['id']) ?>/toggle">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn <?= $plan['is_active'] ? 'btn-danger' : 'btn-secondary' ?> btn-sm"><?= $plan['is_active'] ? 'An' : 'Hien' ?></button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($plans)): ?>
<tr><td colspan="8" class="empty-state">Chua co goi dich vu nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
