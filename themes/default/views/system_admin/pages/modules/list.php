<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Module hệ thống</h1>
<p class="text-muted">Module luôn được bật cho MỌI site (không toggle được theo từng site) — danh sách chỉ mang tính tham khảo.</p>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Key</th><th>Tên</th><th>Phiên bản</th><th>Phụ thuộc</th></tr>
</thead>
<tbody>
<?php foreach ($modules as $module): ?>
<tr>
    <td><code><?= $this->e($module['key']) ?></code></td>
    <td><?= $this->e($module['name']) ?></td>
    <td><?= $this->e($module['version']) ?></td>
    <td><?= $module['dependencies'] === [] ? '-' : $this->e(\implode(', ', $module['dependencies'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
