<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quan ly quyen - <?= $this->e($role['name'] ?? '') ?></h1>

<h2>Permission da gan</h2>
<ul>
<?php foreach ($assigned as $permission): ?>
<li><?= $this->e($permission['key']) ?></li>
<?php endforeach; ?>
</ul>

<h2>Permission chua gan</h2>
<?php if ($isSystem): ?>
<p>System role khong the sua permission.</p>
<?php else: ?>
<ul>
<?php foreach ($unassigned as $permission): ?>
<li>
    <?= $this->e($permission['key']) ?>
    <form method="POST" action="/admin/roles/<?= $this->e((string) $role['id']) ?>/permissions" style="display:inline">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <input type="hidden" name="permission_id" value="<?= $this->e((string) $permission['id']) ?>">
        <button type="submit">Gan</button>
    </form>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php $this->endSection(); ?>
