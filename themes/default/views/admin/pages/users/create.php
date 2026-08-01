<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Tao User</h1>
<?php if (!empty($errors)): ?>
<ul class="errors">
<?php foreach ($errors as $messages): ?>
<?php foreach ($messages as $message): ?>
<li><?= $this->e($message) ?></li>
<?php endforeach; ?>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<form method="POST" action="/admin/users">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <label>Ten
        <input type="text" name="name" value="<?= $this->e($old['name'] ?? '') ?>">
    </label>
    <label>Email
        <input type="email" name="email" value="<?= $this->e($old['email'] ?? '') ?>">
    </label>
    <label>Mat khau
        <input type="password" name="password">
    </label>
    <label>Role
        <select name="role_id">
            <?php foreach ($roles as $role): ?>
            <option value="<?= $this->e((string) $role['id']) ?>"<?= (string) $role['id'] === (string) ($old['role_id'] ?? '') ? ' selected' : '' ?>><?= $this->e($role['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Tao user</button>
</form>
<?php $this->endSection(); ?>
