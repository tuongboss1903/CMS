<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Theme hệ thống</h1>
<p class="text-muted">Gán theme cho site tại trang Sửa Site (/system-admin/sites/{id}/edit).</p>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Key</th><th>Tên</th><th>Phiên bản</th></tr>
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
