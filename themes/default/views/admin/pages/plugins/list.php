<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Plugin</h1>
<p class="text-muted">Bật/tắt plugin cho tenant hiện tại.</p>
<?php if (empty($plugins)): ?>
<?php $this->include('admin.partials.empty_state', [
    'icon' => 'plugins',
    'title' => 'Chưa có plugin nào',
    'description' => 'Plugin được cài đặt qua thư mục plugins/ trên server. Sau khi cài, plugin sẽ xuất hiện ở đây để bật/tắt cho tenant hiện tại.',
]); ?>
<?php else: ?>
<div class="table-wrap table-wrap--flat">
<table class="data-table">
<thead><tr><th scope="col">Tên</th><th scope="col">Key</th><th scope="col">Phiên bản</th><th scope="col">Trạng thái</th><th scope="col"></th></tr></thead>
<tbody>
<?php foreach ($plugins as $plugin): ?>
<tr>
    <td><?= $this->e((string) $plugin['name']) ?><br><span class="text-muted"><?= $this->e((string) $plugin['description']) ?></span></td>
    <td><code><?= $this->e((string) $plugin['key']) ?></code></td>
    <td><?= $this->e((string) $plugin['version']) ?></td>
    <td><span class="status-dot <?= $plugin['is_active'] ? 'status-dot--published' : 'status-dot--draft' ?>"><?= $plugin['is_active'] ? 'Đang bật' : 'Đang tắt' ?></span></td>
    <td>
        <form method="POST" action="/admin/plugins/<?= $this->e((string) $plugin['key']) ?>/toggle" data-confirm="<?= $plugin['is_active'] ? 'Tắt plugin này?' : 'Bật plugin này?' ?>">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="switch<?= $plugin['is_active'] ? ' is-on' : '' ?>" role="switch" aria-checked="<?= $plugin['is_active'] ? 'true' : 'false' ?>" aria-label="<?= $this->e('Bật/tắt plugin ' . (string) $plugin['name']) ?>"></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
