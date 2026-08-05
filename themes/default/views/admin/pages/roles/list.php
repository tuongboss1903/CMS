<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Quản lý Vai trò</h1>
    <a href="/admin/roles/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo vai trò mới</a>
</div>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Tên</th><th>Loại</th><th>Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($roles as $role): ?>
<tr>
    <td><?= $this->e($role['name']) ?></td>
    <td data-field="type">
        <span class="badge <?= $role['system'] ? 'badge-warning' : 'badge-neutral' ?>"><?= $role['system'] ? 'Hệ thống' : 'Tenant' ?></span>
    </td>
    <td>
        <div class="table-actions">
        <a href="/admin/roles/<?= $this->e((string) $role['id']) ?>/permissions" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'roles']); ?> Quản lý quyền</a>

        <?php if (!$role['system']): ?>
        <a href="/admin/roles/<?= $this->e((string) $role['id']) ?>/edit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'edit']); ?> Sửa</a>

        <form method="POST" action="/admin/roles/<?= $this->e((string) $role['id']) ?>/delete" data-confirm="Xác nhận xoá vai trò này?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
        </form>
        <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
