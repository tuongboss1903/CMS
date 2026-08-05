<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Tạo Vai trò</h1>
<div class="card card--spacious" style="max-width: 480px;">
<form method="POST" action="/admin/roles">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field">
        <label for="name">Tên vai trò <span class="req">*</span></label>
        <input type="text" id="name" name="name" value="<?= $this->e($old['name'] ?? '') ?>" required<?= empty($errors['name']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['name'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">Tạo vai trò</button>
        <a href="/admin/roles" class="btn btn-secondary">Huỷ</a>
    </div>
</form>
</div>
<?php $this->endSection(); ?>
