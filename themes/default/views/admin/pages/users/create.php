<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Tạo Người dùng</h1>
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
<form method="POST" action="/admin/users">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field">
        <label for="name">Tên</label>
        <input type="text" id="name" name="name" value="<?= $this->e($old['name'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= $this->e($old['email'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="password">Mật khẩu</label>
        <input type="password" id="password" name="password">
    </div>
    <div class="field">
        <label for="role_id">Vai trò</label>
        <select id="role_id" name="role_id">
            <?php foreach ($roles as $role): ?>
            <option value="<?= $this->e((string) $role['id']) ?>"<?= (string) $role['id'] === (string) ($old['role_id'] ?? '') ? ' selected' : '' ?>><?= $this->e($role['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo người dùng</button>
        <a href="/admin/users" class="btn btn-secondary">Huỷ</a>
    </div>
</form>
</div>
<?php $this->endSection(); ?>
