<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Dang nhap Admin</h1>
<?php if (!empty($errors)): ?>
<ul class="errors">
<?php foreach ($errors as $messages): ?>
<?php foreach ($messages as $message): ?>
<li><?= $this->e($message) ?></li>
<?php endforeach; ?>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<form method="POST" action="/admin/login">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <label>Email
        <input type="email" name="email" value="<?= $this->e($old['email'] ?? '') ?>">
    </label>
    <label>Mat khau
        <input type="password" name="password">
    </label>
    <button type="submit">Dang nhap</button>
</form>
<?php $this->endSection(); ?>
