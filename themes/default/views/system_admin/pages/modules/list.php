<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Module he thong</h1>
<p class="text-muted">Module luon duoc bat cho MOI site (khong toggle duoc theo tung site) - danh sach chi mang tinh tham khao.</p>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Key</th><th>Ten</th><th>Version</th><th>Dependencies</th></tr>
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
