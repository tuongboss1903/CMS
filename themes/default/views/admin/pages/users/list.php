<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quan ly User</h1>
<p><a href="/admin/users/create">Tao user moi</a></p>
<table>
<thead>
<tr><th>Ten</th><th>Email</th><th>Trang thai</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr>
    <td><?= $this->e($user['name']) ?></td>
    <td><?= $this->e($user['email']) ?></td>
    <td data-field="status"><?= $this->e($user['status']) ?></td>
    <td>
        <a href="/admin/users/<?= $this->e((string) $user['id']) ?>/edit">Sua</a>

        <?php if ($user['status'] === 'active'): ?>
        <form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>/lock" style="display:inline">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit">Khoa</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>/unlock" style="display:inline">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit">Mo khoa</button>
        </form>
        <?php endif; ?>

        <form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>/role" style="display:inline">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <select name="role_id">
                <?php foreach ($roles as $role): ?>
                <option value="<?= $this->e((string) $role['id']) ?>"><?= $this->e($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Gan role</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php $this->endSection(); ?>
