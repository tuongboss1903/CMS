<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between mb-5">
    <h1 class="mb-0">Gói dịch vụ</h1>
    <a href="/system-admin/plans/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo gói mới</a>
</div>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th scope="col">Key</th><th scope="col">Tên</th><th scope="col">Giá (VND)</th><th scope="col">Chu kỳ</th><th scope="col">Giới hạn</th><th scope="col">Số Site</th><th scope="col">Trạng thái</th><th scope="col">Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($plans as $plan): ?>
<tr>
    <td><code><?= $this->e((string) $plan['key']) ?></code></td>
    <td><?= $this->e((string) $plan['name']) ?></td>
    <td><?= $this->e(\number_format((float) $plan['price_vnd'], 0, ',', '.')) ?></td>
    <td><?= $plan['billing_cycle'] === 'yearly' ? 'Hàng năm' : 'Hàng tháng' ?></td>
    <td class="text-muted">
        Người dùng: <?= $this->e((string) ($plan['max_users'] ?? 'Không giới hạn')) ?>,
        Dung lượng: <?= $this->e((string) ($plan['max_storage_mb'] ?? 'Không giới hạn')) ?> MB,
        Sản phẩm: <?= $this->e((string) ($plan['max_products'] ?? 'Không giới hạn')) ?>
    </td>
    <td><?= $this->e((string) $plan['site_count']) ?></td>
    <td data-field="status">
        <span class="badge <?= $plan['is_active'] ? 'badge-success' : 'badge-secondary' ?>"><?= $plan['is_active'] ? 'Đang bán' : 'Đã ẩn' ?></span>
    </td>
    <td>
        <div class="table-actions">
        <a href="/system-admin/plans/<?= $this->e((string) $plan['id']) ?>/edit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'edit']); ?> Sửa</a>
        <form method="POST" action="/system-admin/plans/<?= $this->e((string) $plan['id']) ?>/toggle">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn <?= $plan['is_active'] ? 'btn-danger' : 'btn-secondary' ?> btn-sm"><?= $plan['is_active'] ? 'Ẩn' : 'Hiện' ?></button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($plans)): ?>
<tr><td colspan="8" class="empty-state">Chưa có gói dịch vụ nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
