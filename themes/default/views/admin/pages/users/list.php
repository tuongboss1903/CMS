<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Quản lý Người dùng</h1>
    <a href="/admin/users/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo người dùng mới</a>
</div>

<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/admin/users',
    'filter_fields' => [
        ['name' => 'q', 'label' => 'Tìm theo tên/email', 'type' => 'text', 'value' => $filters['q'] ?? ''],
        ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'value' => $filters['status'] ?? '', 'options' => [
            ['value' => 'active', 'label' => 'Đang hoạt động'],
            ['value' => 'locked', 'label' => 'Đã khoá'],
        ]],
    ],
]); ?>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Tên</th><th>Email</th><th>Trạng thái</th><th>Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr>
    <td><?= $this->e($user['name']) ?></td>
    <td><?= $this->e($user['email']) ?></td>
    <td data-field="status">
        <span class="badge <?= $user['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= $user['status'] === 'active' ? 'Đang hoạt động' : 'Đã khoá' ?></span>
    </td>
    <td>
        <div class="table-actions">
        <a href="/admin/users/<?= $this->e((string) $user['id']) ?>/edit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'edit']); ?> Sửa</a>

        <?php if ($user['status'] === 'active'): ?>
        <form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>/lock" data-confirm="Khoá tài khoản này? Người dùng sẽ không thể đăng nhập.">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'lock']); ?> Khoá</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>/unlock" data-confirm="Mở khoá tài khoản này?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'unlock']); ?> Mở khoá</button>
        </form>
        <?php endif; ?>

        <form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>/role" class="flex gap-2">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <select name="role_id">
                <?php foreach ($roles as $role): ?>
                <option value="<?= $this->e((string) $role['id']) ?>"><?= $this->e($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'assign']); ?> Gán</button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
