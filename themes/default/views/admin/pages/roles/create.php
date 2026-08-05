<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Tạo Vai trò</h1>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
<ul>
<?php foreach ($errors as $messages): ?>
<?php foreach ($messages as $message): ?>
<li><?= $this->e($message) ?></li>
<?php endforeach; ?>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<div class="card" style="max-width: 480px;">
<form method="POST" action="/admin/roles">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field">
        <label for="name">Tên vai trò</label>
        <input type="text" id="name" name="name" value="<?= $this->e($old['name'] ?? '') ?>">
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">Tạo vai trò</button>
        <a href="/admin/roles" class="btn btn-secondary">Huỷ</a>
    </div>
</form>
</div>
<?php $this->endSection(); ?>
