<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Tạo Người dùng</h1>
<?php
$knownFields = ['name', 'email', 'password', 'role_id'];
$otherErrors = \array_diff_key($errors, \array_flip($knownFields));
?>
<?php if (!empty($otherErrors)): ?>
<div class="alert alert-danger">
<ul>
<?php foreach ($otherErrors as $messages): ?>
<?php foreach ($messages as $message): ?>
<li><?= $this->e($message) ?></li>
<?php endforeach; ?>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<div class="card card--spacious" style="max-width: 480px;">
<form method="POST" action="/admin/users">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field-grid-2">
    <div class="field">
        <label for="name">Tên <span class="req">*</span></label>
        <input type="text" id="name" name="name" value="<?= $this->e($old['name'] ?? '') ?>" required<?= empty($errors['name']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['name'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="email">Email <span class="req">*</span></label>
        <input type="email" id="email" name="email" value="<?= $this->e($old['email'] ?? '') ?>" required<?= empty($errors['email']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['email'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    </div>
    <div class="field">
        <label for="password">Mật khẩu <span class="req">*</span></label>
        <input type="password" id="password" name="password" required<?= empty($errors['password']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['password'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="role_id">Vai trò <span class="req">*</span></label>
        <select id="role_id" name="role_id" required<?= empty($errors['role_id']) ? '' : ' aria-invalid="true"' ?>>
            <?php foreach ($roles as $role): ?>
            <option value="<?= $this->e((string) $role['id']) ?>"<?= (string) $role['id'] === (string) ($old['role_id'] ?? '') ? ' selected' : '' ?>><?= $this->e($role['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php foreach ($errors['role_id'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo người dùng</button>
        <a href="/admin/users" class="btn btn-secondary">Huỷ</a>
    </div>
</form>
</div>
<?php $this->endSection(); ?>
