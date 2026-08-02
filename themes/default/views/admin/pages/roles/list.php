<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Quan ly Role</h1>
    <a href="/admin/roles/create" class="btn btn-primary">+ Tao role moi</a>
</div>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Ten</th><th>Loai</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($roles as $role): ?>
<tr>
    <td><?= $this->e($role['name']) ?></td>
    <td data-field="type">
        <span class="badge <?= $role['system'] ? 'badge-warning' : 'badge-neutral' ?>"><?= $role['system'] ? 'System' : 'Tenant' ?></span>
    </td>
    <td>
        <div class="table-actions">
        <a href="/admin/roles/<?= $this->e((string) $role['id']) ?>/permissions" class="btn btn-secondary btn-sm">Quan ly quyen</a>

        <?php if (!$role['system']): ?>
        <a href="/admin/roles/<?= $this->e((string) $role['id']) ?>/edit" class="btn btn-secondary btn-sm">Sua</a>

        <form method="POST" action="/admin/roles/<?= $this->e((string) $role['id']) ?>/delete" data-confirm="Xac nhan xoa role nay?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
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
