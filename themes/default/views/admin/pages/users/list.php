<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between mb-5">
    <h1 class="mb-0">Quản lý Người dùng</h1>
    <a href="/admin/users/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo người dùng mới</a>
</div>

<?php if (isset($stats)): ?>
<div class="stat-grid mb-5">
    <div class="stat-card">
        <div class="stat-label">Tổng số người dùng</div>
        <div class="stat-value"><?= $this->e((string) $stats['total']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Đang hoạt động</div>
        <div class="stat-value"><?= $this->e((string) $stats['active']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Đã khoá</div>
        <div class="stat-value"><?= $this->e((string) $stats['locked']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Vai trò khả dụng</div>
        <div class="stat-value"><?= $this->e((string) $stats['roles']) ?></div>
    </div>
</div>
<?php endif; ?>

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

<div class="table-wrap table-wrap--flat">
<table class="data-table">
<thead>
<tr><th scope="col">Tên</th><th scope="col">Email</th><th scope="col">Trạng thái</th><th scope="col">Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr>
    <td><a href="/admin/users/<?= $this->e((string) $user['id']) ?>/edit" class="row-title-link"><?= $this->e($user['name']) ?></a></td>
    <td><?= $this->e($user['email']) ?></td>
    <td data-field="status">
        <span class="status-dot <?= $user['status'] === 'active' ? 'status-dot--published' : 'status-dot--archived' ?>"><?= $user['status'] === 'active' ? 'Đang hoạt động' : 'Đã khoá' ?></span>
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
            <select name="role_id" aria-label="Chọn vai trò để gán cho <?= $this->e($user['name']) ?>">
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
