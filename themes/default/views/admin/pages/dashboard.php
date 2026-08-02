<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Dashboard</h1>
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Tong so User</div>
        <div class="stat-value" data-field="user_count"><?= $this->e((string) $user_count) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tong so Role</div>
        <div class="stat-value" data-field="role_count"><?= $this->e((string) $role_count) ?></div>
    </div>
</div>
<div class="card" style="margin-top: var(--space-5);">
    <form method="POST" action="/admin/logout">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
        <button type="submit" class="btn btn-secondary">Dang xuat</button>
    </form>
</div>
<?php $this->endSection(); ?>
