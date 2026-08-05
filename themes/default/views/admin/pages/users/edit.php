<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Sửa Người dùng</h1>
<?php
$knownFields = ['name', 'email'];
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
<form method="POST" action="/admin/users/<?= $this->e((string) $user['id']) ?>">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field-grid-2">
    <div class="field">
        <label for="name">Tên <span class="opt">(tuỳ chọn)</span></label>
        <input type="text" id="name" name="name" value="<?= $this->e($old['name'] ?? '') ?>"<?= empty($errors['name']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['name'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="email">Email <span class="opt">(tuỳ chọn)</span></label>
        <input type="email" id="email" name="email" value="<?= $this->e($old['email'] ?? '') ?>"<?= empty($errors['email']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['email'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="/admin/users" class="btn btn-secondary">Huỷ</a>
    </div>
</form>
</div>
<?php $this->endSection(); ?>
