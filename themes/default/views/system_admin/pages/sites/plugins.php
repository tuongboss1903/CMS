<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between mb-5">
    <h1 class="mb-0">Plugin của site: <?= $this->e((string) $site['name']) ?></h1>
    <a href="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/edit" class="btn btn-secondary">Quay lại Site</a>
</div>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th scope="col">Plugin</th><th scope="col">Phiên bản</th><th scope="col">Mô tả</th><th scope="col">Trạng thái</th><th scope="col">Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($plugins as $plugin): ?>
<tr>
    <td><?= $this->e($plugin['name']) ?></td>
    <td><?= $this->e($plugin['version']) ?></td>
    <td class="text-muted"><?= $this->e($plugin['description']) ?></td>
    <td data-field="status">
        <span class="badge <?= $plugin['is_active'] ? 'badge-success' : 'badge-secondary' ?>"><?= $plugin['is_active'] ? 'Đang bật' : 'Đang tắt' ?></span>
    </td>
    <td>
        <form method="POST" action="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/plugins/<?= $this->e($plugin['key']) ?>/toggle" data-confirm="<?= $plugin['is_active'] ? 'Tắt plugin này cho site?' : 'Bật plugin này cho site?' ?>">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn <?= $plugin['is_active'] ? 'btn-danger' : 'btn-secondary' ?> btn-sm"><?= $plugin['is_active'] ? 'Tắt' : 'Bật' ?></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
