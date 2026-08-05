<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Quản lý Site</h1>
    <a href="/system-admin/sites/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo site mới</a>
</div>

<?php
$siteStatusLabels = ['active' => 'Đang hoạt động', 'maintenance' => 'Bảo trì', 'suspended' => 'Tạm khoá'];
?>
<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/system-admin/sites',
    'filter_fields' => [
        ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'value' => $filters['status'] ?? '', 'options' => [
            ['value' => 'active', 'label' => 'Đang hoạt động'],
            ['value' => 'maintenance', 'label' => 'Bảo trì'],
            ['value' => 'suspended', 'label' => 'Tạm khoá'],
        ]],
    ],
]); ?>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Tên Site</th><th>Domain</th><th>Theme</th><th>Trạng thái</th><th>Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($sites as $site): ?>
<tr>
    <td><?= $this->e((string) $site['name']) ?></td>
    <td>
        <?php foreach ($site['domains'] as $domain): ?>
        <span class="badge <?= $domain['is_primary'] ? 'badge-success' : 'badge-secondary' ?>"><?= $this->e((string) $domain['domain']) ?></span>
        <?php endforeach; ?>
    </td>
    <td><?= $this->e((string) ($site['theme_active'] ?? 'default')) ?></td>
    <td data-field="status">
        <span class="badge <?= $site['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= $this->e($siteStatusLabels[$site['status']] ?? (string) $site['status']) ?></span>
    </td>
    <td>
        <div class="table-actions">
        <a href="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/edit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'edit']); ?> Sửa</a>

        <?php if ($site['status'] === 'active'): ?>
        <form method="POST" action="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/suspend" data-confirm="Tạm khoá site này? Toàn bộ request tới site sẽ bị chặn.">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'lock']); ?> Tạm khoá</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/activate">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'unlock']); ?> Kích hoạt</button>
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
