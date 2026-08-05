<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Plugin</h1>
<p class="text-muted">Bật/tắt plugin cho tenant hiện tại.</p>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th scope="col">Tên</th><th scope="col">Key</th><th scope="col">Phiên bản</th><th scope="col">Trạng thái</th><th scope="col"></th></tr></thead>
<tbody>
<?php foreach ($plugins as $plugin): ?>
<tr>
    <td><?= $this->e((string) $plugin['name']) ?><br><span class="text-muted"><?= $this->e((string) $plugin['description']) ?></span></td>
    <td><code><?= $this->e((string) $plugin['key']) ?></code></td>
    <td><?= $this->e((string) $plugin['version']) ?></td>
    <td><span class="badge <?= $plugin['is_active'] ? 'badge-success' : 'badge-neutral' ?>"><?= $plugin['is_active'] ? 'Đang bật' : 'Đang tắt' ?></span></td>
    <td>
        <form method="POST" action="/admin/plugins/<?= $this->e((string) $plugin['key']) ?>/toggle" data-confirm="<?= $plugin['is_active'] ? 'Tắt plugin này?' : 'Bật plugin này?' ?>">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn <?= $plugin['is_active'] ? 'btn-secondary' : 'btn-primary' ?> btn-sm"><?= $plugin['is_active'] ? 'Tắt' : 'Bật' ?></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($plugins)): ?>
<tr><td colspan="5" class="empty-state">Chưa có plugin nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
