<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Sua Role</h1>
<?php if (!empty($errors)): ?>
<ul class="errors">
<?php foreach ($errors as $messages): ?>
<?php foreach ($messages as $message): ?>
<li><?= $this->e($message) ?></li>
<?php endforeach; ?>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<form method="POST" action="/admin/roles/<?= $this->e((string) $role['id']) ?>">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <label>Ten role
        <input type="text" name="name" value="<?= $this->e($old['name'] ?? '') ?>">
    </label>
    <button type="submit">Cap nhat</button>
</form>
<?php $this->endSection(); ?>
