<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Theme he thong</h1>
<p class="text-muted">Gan theme cho site tai trang Sua Site (/system-admin/sites/{id}/edit).</p>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Key</th><th>Ten</th><th>Version</th></tr>
</thead>
<tbody>
<?php foreach ($themes as $theme): ?>
<tr>
    <td><code><?= $this->e($theme['key']) ?></code></td>
    <td><?= $this->e($theme['name']) ?></td>
    <td><?= $this->e($theme['version']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
