<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Cấu hình hệ thống</h1>
<p class="text-muted">Cấu hình dạng key-value theo nhóm — bổ sung cho <a href="/admin/settings">Cài đặt chung</a> (tên website/favicon...).</p>

<?php foreach ($grouped as $group => $items): ?>
<div class="card mt-4">
    <h2 style="font-size:16px; text-transform: capitalize;"><?= $this->e($group) ?></h2>
    <div class="table-wrap table-wrap--flat">
    <table class="data-table">
    <thead>
    <tr><th scope="col">Key</th><th scope="col">Giá trị</th><th scope="col"></th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
    <tr>
        <td><code><?= $this->e($item['key']) ?></code><?php if ($item['is_encrypted']): ?> <span class="badge badge-warning">Đã mã hoá</span><?php endif; ?></td>
        <td><?= $this->e((string) $item['value']) ?></td>
        <td>
        <form method="POST" action="/admin/system-settings/<?= $this->e((string) $item['id']) ?>/delete" style="display:inline;" data-confirm="Xoá cấu hình này?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
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
<div class="card mt-4"><p class="empty-state">Chưa có cấu hình nào.</p></div>
<?php endif; ?>

<div class="card mt-5" style="max-width: 520px;">
    <h2 style="font-size:16px;">Thêm / Cập nhật cấu hình</h2>
    <form method="POST" action="/admin/system-settings">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <div class="field">
            <label for="key">Key</label>
            <input type="text" id="key" name="key" placeholder="mail.smtp_password" required>
        </div>
        <div class="field">
            <label for="setting_group">Nhóm</label>
            <input type="text" id="setting_group" name="setting_group" placeholder="general / mail / security" value="general">
        </div>
        <div class="field">
            <label for="value">Giá trị</label>
            <textarea id="value" name="value" rows="3"></textarea>
        </div>
        <div class="field">
            <label><input type="checkbox" name="is_encrypted" value="1"> Mã hoá giá trị này (vd mật khẩu/API key)</label>
        </div>
        <button type="submit" class="btn btn-primary">Lưu</button>
    </form>
</div>
<?php $this->endSection(); ?>
