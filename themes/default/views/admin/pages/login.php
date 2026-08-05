<?php $this->extend('admin.layouts.auth'); ?>
<?php $this->section('content'); ?>
<div class="brand"><?php $this->include('admin.partials.icon', ['name' => 'lock', 'class' => 'icon brand-icon']); ?> CMS<span class="dot">.</span>Admin</div>
<h1>Đăng nhập hệ thống quản trị</h1>
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
<form method="POST" action="/admin/login">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= $this->e($old['email'] ?? '') ?>" autofocus required>
    </div>
    <div class="field">
        <label for="password">Mật khẩu</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
</form>
<?php $this->endSection(); ?>
