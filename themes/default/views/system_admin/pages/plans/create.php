<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Tạo gói dịch vụ mới</h1>
<?php
$knownFields = ['key', 'name', 'price_vnd', 'billing_cycle', 'max_users', 'max_storage_mb', 'max_products'];
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
<form method="POST" action="/system-admin/plans" class="card card--spacious" style="max-width: 480px;">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <div class="field-grid-2">
    <div class="field">
        <label for="key">Key <span class="req">*</span></label>
        <input type="text" id="key" name="key" value="<?= $this->e((string) ($old['key'] ?? '')) ?>" placeholder="basic" autofocus required<?= empty($errors['key']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['key'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="name">Tên gói <span class="req">*</span></label>
        <input type="text" id="name" name="name" value="<?= $this->e((string) ($old['name'] ?? '')) ?>" placeholder="Cơ bản" required<?= empty($errors['name']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['name'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    </div>
    <div class="field-grid-2">
    <div class="field">
        <label for="price_vnd">Giá (VND) <span class="opt">(tuỳ chọn)</span></label>
        <input type="number" id="price_vnd" name="price_vnd" value="<?= $this->e((string) ($old['price_vnd'] ?? '0')) ?>" min="0"<?= empty($errors['price_vnd']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['price_vnd'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="billing_cycle">Chu kỳ <span class="opt">(tuỳ chọn)</span></label>
        <select id="billing_cycle" name="billing_cycle"<?= empty($errors['billing_cycle']) ? '' : ' aria-invalid="true"' ?>>
            <option value="monthly"<?= (string) ($old['billing_cycle'] ?? 'monthly') === 'monthly' ? ' selected' : '' ?>>Hàng tháng</option>
            <option value="yearly"<?= (string) ($old['billing_cycle'] ?? '') === 'yearly' ? ' selected' : '' ?>>Hàng năm</option>
        </select>
        <?php foreach ($errors['billing_cycle'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    </div>
    <div class="field">
        <label for="max_users">Giới hạn số Người dùng <span class="opt">(bỏ trống = không giới hạn)</span></label>
        <input type="number" id="max_users" name="max_users" value="<?= $this->e((string) ($old['max_users'] ?? '')) ?>" min="1"<?= empty($errors['max_users']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['max_users'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="max_storage_mb">Giới hạn dung lượng Media (MB) <span class="opt">(bỏ trống = không giới hạn)</span></label>
        <input type="number" id="max_storage_mb" name="max_storage_mb" value="<?= $this->e((string) ($old['max_storage_mb'] ?? '')) ?>" min="1"<?= empty($errors['max_storage_mb']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['max_storage_mb'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="max_products">Giới hạn số Sản phẩm <span class="opt">(bỏ trống = không giới hạn)</span></label>
        <input type="number" id="max_products" name="max_products" value="<?= $this->e((string) ($old['max_products'] ?? '')) ?>" min="1"<?= empty($errors['max_products']) ? '' : ' aria-invalid="true"' ?>>
        <?php foreach ($errors['max_products'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo Gói</button>
    <a href="/system-admin/plans" class="btn btn-secondary">Huỷ</a>
</form>
<?php $this->endSection(); ?>
