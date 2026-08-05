<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Sửa Người dùng</h1>
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
<form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field">
        <label for="name">Tên</label>
        <input type="text" id="name" name="name" value="<?= $this->e($old['name'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= $this->e($old['email'] ?? '') ?>">
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="/admin/users" class="btn btn-secondary">Huỷ</a>
    </div>
</form>
</div>
<?php $this->endSection(); ?>
