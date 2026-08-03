<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>System Settings</h1>
<p class="text-muted">Cau hinh dang key-value theo nhom - bo sung cho <a href="/admin/settings">Cai dat chung</a> (site_name/favicon...).</p>

<?php foreach ($grouped as $group => $items): ?>
<div class="card" style="margin-top: var(--space-4);">
    <h2 style="font-size:16px; margin-top:0; text-transform: capitalize;"><?= $this->e($group) ?></h2>
    <div class="table-wrap">
    <table class="data-table">
    <thead>
    <tr><th>Key</th><th>Value</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
    <tr>
        <td><code><?= $this->e($item['key']) ?></code><?php if ($item['is_encrypted']): ?> <span class="badge badge-warning">Encrypted</span><?php endif; ?></td>
        <td><?= $this->e((string) $item['value']) ?></td>
        <td>
        <form method="POST" action="/admin/system-settings/<?= $this->e((string) $item['id']) ?>/delete" style="display:inline;" data-confirm="Xoa setting nay?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
        </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($grouped)): ?>
<div class="card" style="margin-top: var(--space-4);"><p class="empty-state">Chua co setting nao.</p></div>
<?php endif; ?>

<div class="card" style="margin-top: var(--space-5); max-width: 520px;">
    <h2 style="font-size:16px; margin-top:0;">Them / Cap nhat Setting</h2>
    <form method="POST" action="/admin/system-settings">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <div class="field">
            <label for="key">Key</label>
            <input type="text" id="key" name="key" placeholder="mail.smtp_password" required>
        </div>
        <div class="field">
            <label for="setting_group">Nhom</label>
            <input type="text" id="setting_group" name="setting_group" placeholder="general / mail / security" value="general">
        </div>
        <div class="field">
            <label for="value">Value</label>
            <textarea id="value" name="value" rows="3"></textarea>
        </div>
        <div class="field">
            <label><input type="checkbox" name="is_encrypted" value="1"> Ma hoa gia tri nay (vd mat khau/API key)</label>
        </div>
        <button type="submit" class="btn btn-primary">Luu</button>
    </form>
</div>
<?php $this->endSection(); ?>
