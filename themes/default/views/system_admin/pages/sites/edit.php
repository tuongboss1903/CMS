<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Sửa site: <?= $this->e((string) $site['name']) ?></h1>
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
<form method="POST" action="/system-admin/sites/<?= $this->e((string) $site['id']) ?>" class="card" style="max-width: 480px;">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <div class="field">
        <label for="name">Tên Site</label>
        <input type="text" id="name" name="name" value="<?= $this->e($old['name'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="theme_active">Theme</label>
        <select id="theme_active" name="theme_active">
            <option value="">-- Dùng theme mặc định hệ thống --</option>
            <?php foreach (($themes ?? []) as $theme): ?>
            <option value="<?= $this->e($theme->key) ?>"<?= $theme->key === (string) ($site['theme_active'] ?? '') ? ' selected' : '' ?>><?= $this->e($theme->name) ?> (<?= $this->e($theme->version) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="plan_id">Gói dịch vụ</label>
        <select id="plan_id" name="plan_id">
            <option value="">-- Không gán gói --</option>
            <?php foreach (($plans ?? []) as $plan): ?>
            <option value="<?= $this->e((string) $plan['id']) ?>"<?= (int) $plan['id'] === (int) ($site['plan_id'] ?? 0) ? ' selected' : '' ?>><?= $this->e((string) $plan['name']) ?><?= $plan['is_active'] ? '' : ' (đã ẩn)' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Lưu</button>
    <a href="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/plugins" class="btn btn-secondary"><?php $this->include('admin.partials.icon', ['name' => 'plugins']); ?> Quản lý Plugin</a>
    <a href="/system-admin/plans" class="btn btn-secondary"><?php $this->include('admin.partials.icon', ['name' => 'billing']); ?> Quản lý Gói dịch vụ</a>
</form>

<h2>Domain</h2>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th scope="col">Domain</th><th scope="col">Chính</th><th scope="col">Hành động</th></tr></thead>
<tbody>
<?php foreach ($domains as $domain): ?>
<tr>
    <td><?= $this->e((string) $domain['domain']) ?></td>
    <td><?= $domain['is_primary'] ? 'Có' : '' ?></td>
    <td>
        <?php if (!$domain['is_primary']): ?>
        <form method="POST" action="/system-admin/site-domains/<?= $this->e((string) $domain['id']) ?>/delete" data-confirm="Xoá domain phụ này?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<form method="POST" action="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/domains" class="flex gap-2" style="margin-top: var(--space-3);">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <input type="text" name="domain" placeholder="domain-phu.com">
    <button type="submit" class="btn btn-secondary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Thêm domain</button>
</form>
<?php $this->endSection(); ?>
