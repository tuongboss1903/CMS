<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Quan ly User</h1>
    <a href="/admin/users/create" class="btn btn-primary">+ Tao user moi</a>
</div>

<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/admin/users',
    'filter_fields' => [
        ['name' => 'q', 'label' => 'Tim theo ten/email', 'type' => 'text', 'value' => $filters['q'] ?? ''],
        ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'value' => $filters['status'] ?? '', 'options' => [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'locked', 'label' => 'Locked'],
        ]],
    ],
]); ?>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Ten</th><th>Email</th><th>Trang thai</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr>
    <td><?= $this->e($user['name']) ?></td>
    <td><?= $this->e($user['email']) ?></td>
    <td data-field="status">
        <span class="badge <?= $user['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= $this->e($user['status']) ?></span>
    </td>
    <td>
        <div class="table-actions">
        <a href="/admin/users/<?= $this->e((string) $user['id']) ?>/edit" class="btn btn-secondary btn-sm">Sua</a>

        <?php if ($user['status'] === 'active'): ?>
        <form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>/lock">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Khoa</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>/unlock">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Mo khoa</button>
        </form>
        <?php endif; ?>

        <form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>/role" class="flex gap-2">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <select name="role_id">
                <?php foreach ($roles as $role): ?>
                <option value="<?= $this->e((string) $role['id']) ?>"><?= $this->e($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Gan</button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
