<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Tạo site mới</h1>
<form method="POST" action="/system-admin/sites" class="card card--spacious" style="max-width: 480px;">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <div class="field-grid-2">
    <div class="field">
        <label for="name">Tên Site <span class="req">*</span></label>
        <input type="text" id="name" name="name" value="<?= $this->e($old['name'] ?? '') ?>" autofocus required<?= empty($errors['name']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['name'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="domain">Domain chính <span class="req">*</span></label>
        <input type="text" id="domain" name="domain" value="<?= $this->e($old['domain'] ?? '') ?>" placeholder="vidu.com" required<?= empty($errors['domain']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['domain'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    </div>
    <button type="submit" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo Site</button>
    <a href="/system-admin/sites" class="btn btn-secondary">Huỷ</a>
</form>
<?php $this->endSection(); ?>
