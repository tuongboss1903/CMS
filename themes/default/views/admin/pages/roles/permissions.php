<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quan ly quyen - <?= $this->e($role['name'] ?? '') ?></h1>

<div class="card">
<h2>Permission da gan</h2>
<?php if (empty($assigned)): ?>
<p class="text-muted">Chua co permission nao duoc gan.</p>
<?php else: ?>
<ul class="flex gap-2" style="flex-wrap: wrap;">
<?php foreach ($assigned as $permission): ?>
<li class="badge badge-success"><?= $this->e($permission['key']) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>

<div class="card" style="margin-top: var(--space-5);">
<h2>Permission chua gan</h2>
<?php if ($isSystem): ?>
<p class="text-muted">System role khong the sua permission.</p>
<?php elseif (empty($unassigned)): ?>
<p class="text-muted">Da gan toan bo permission.</p>
<?php else: ?>
<div class="table-wrap">
<table class="data-table">
<tbody>
<?php foreach ($unassigned as $permission): ?>
<tr>
    <td><?= $this->e($permission['key']) ?></td>
    <td style="width: 120px; text-align: right;">
    <form method="POST" action="/admin/roles/<?= $this->e((string) $role['id']) ?>/permissions">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <input type="hidden" name="permission_id" value="<?= $this->e((string) $permission['id']) ?>">
        <button type="submit" class="btn btn-secondary btn-sm">Gan</button>
    </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
