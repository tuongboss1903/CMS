<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quan ly Role</h1>
<p><a href="/admin/roles/create">Tao role moi</a></p>
<table>
<thead>
<tr><th>Ten</th><th>Loai</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($roles as $role): ?>
<tr>
    <td><?= $this->e($role['name']) ?></td>
    <td data-field="type"><?= $role['system'] ? 'System' : 'Tenant' ?></td>
    <td>
        <a href="/admin/roles/<?= $this->e((string) $role['id']) ?>/permissions">Quan ly quyen</a>

        <?php if (!$role['system']): ?>
        <a href="/admin/roles/<?= $this->e((string) $role['id']) ?>/edit">Sua</a>

        <form method="POST" action="/admin/roles/<?= $this->e((string) $role['id']) ?>/delete" style="display:inline">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit">Xoa</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php $this->endSection(); ?>
