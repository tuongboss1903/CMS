<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Plugins</h1>
<p class="text-muted">Bat/tat plugin cho tenant hien tai - dong Technical Debt #9 (Phase 19, CMS-056).</p>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Ten</th><th>Key</th><th>Phien ban</th><th>Trang thai</th><th></th></tr></thead>
<tbody>
<?php foreach ($plugins as $plugin): ?>
<tr>
    <td><?= $this->e((string) $plugin['name']) ?><br><span class="text-muted"><?= $this->e((string) $plugin['description']) ?></span></td>
    <td><code><?= $this->e((string) $plugin['key']) ?></code></td>
    <td><?= $this->e((string) $plugin['version']) ?></td>
    <td><span class="badge <?= $plugin['is_active'] ? 'badge-success' : 'badge-neutral' ?>"><?= $plugin['is_active'] ? 'Dang bat' : 'Dang tat' ?></span></td>
    <td>
        <form method="POST" action="/admin/plugins/<?= $this->e((string) $plugin['key']) ?>/toggle" data-confirm="<?= $plugin['is_active'] ? 'Tat plugin nay?' : 'Bat plugin nay?' ?>">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn <?= $plugin['is_active'] ? 'btn-secondary' : 'btn-primary' ?> btn-sm"><?= $plugin['is_active'] ? 'Tat' : 'Bat' ?></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($plugins)): ?>
<tr><td colspan="5" class="empty-state">Chua co plugin nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
