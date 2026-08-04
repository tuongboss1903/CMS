<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Sua goi: <?= $this->e((string) $plan['name']) ?></h1>
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
<form method="POST" action="/system-admin/plans/<?= $this->e((string) $plan['id']) ?>" class="card" style="max-width: 480px;">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <div class="field">
        <label for="key">Key</label>
        <input type="text" id="key" name="key" value="<?= $this->e((string) ($old['key'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="name">Ten goi</label>
        <input type="text" id="name" name="name" value="<?= $this->e((string) ($old['name'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="price_vnd">Gia (VND)</label>
        <input type="number" id="price_vnd" name="price_vnd" value="<?= $this->e((string) ($old['price_vnd'] ?? '0')) ?>" min="0">
    </div>
    <div class="field">
        <label for="billing_cycle">Chu ky</label>
        <select id="billing_cycle" name="billing_cycle">
            <option value="monthly"<?= (string) ($old['billing_cycle'] ?? 'monthly') === 'monthly' ? ' selected' : '' ?>>Hang thang</option>
            <option value="yearly"<?= (string) ($old['billing_cycle'] ?? '') === 'yearly' ? ' selected' : '' ?>>Hang nam</option>
        </select>
    </div>
    <div class="field">
        <label for="max_users">Gioi han so User (bo trong = khong gioi han)</label>
        <input type="number" id="max_users" name="max_users" value="<?= $this->e((string) ($old['max_users'] ?? '')) ?>" min="1">
    </div>
    <div class="field">
        <label for="max_storage_mb">Gioi han dung luong Media (MB, bo trong = khong gioi han)</label>
        <input type="number" id="max_storage_mb" name="max_storage_mb" value="<?= $this->e((string) ($old['max_storage_mb'] ?? '')) ?>" min="1">
    </div>
    <div class="field">
        <label for="max_products">Gioi han so San pham (bo trong = khong gioi han)</label>
        <input type="number" id="max_products" name="max_products" value="<?= $this->e((string) ($old['max_products'] ?? '')) ?>" min="1">
    </div>
    <button type="submit" class="btn btn-primary">Luu</button>
    <a href="/system-admin/plans" class="btn btn-secondary">Huy</a>
</form>
<?php $this->endSection(); ?>
