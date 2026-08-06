<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Cấu hình Email</h1>
<p class="text-muted mb-5">Cấu hình gửi email hiện đọc từ file <code>.env</code> của server (không chỉnh sửa qua giao diện này) - xem <code>DEPLOYMENT.md</code> để đổi giá trị. Trang này chỉ hiển thị cấu hình đang hiệu lực và cho phép gửi thử.</p>

<div class="card mb-5">
    <h2>Cấu hình hiện tại</h2>
    <div class="field"><label>Driver</label><p><?= $this->e($driver === 'smtp' ? 'SMTP' : 'Log (chỉ ghi file, không gửi thật)') ?></p></div>
    <div class="field"><label>Địa chỉ gửi (From)</label><p><?= $this->e($from_name) ?> &lt;<?= $this->e($from_address) ?>&gt;</p></div>
    <?php if ($driver === 'smtp'): ?>
    <div class="field"><label>SMTP Host</label><p><code><?= $this->e($smtp_host) ?>:<?= $this->e($smtp_port) ?></code></p></div>
    <div class="field"><label>Mã hoá</label><p><?= $this->e(\strtoupper($smtp_encryption)) ?></p></div>
    <div class="field"><label>Username</label><p><?= $this->e($smtp_username !== '' ? $smtp_username : '—') ?></p></div>
    <div class="field"><label>Password</label><p><?= $smtp_has_password ? '••••••••' : '—' ?></p></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Gửi email thử</h2>
    <p class="text-muted">Xác minh cấu hình đang hoạt động bằng cách gửi 1 email thử tới địa chỉ bất kỳ.</p>
    <form method="POST" action="/admin/email-settings/test">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <div class="field">
            <label for="to">Địa chỉ email nhận thử</label>
            <input type="email" id="to" name="to" required placeholder="ban@example.com">
        </div>
        <button type="submit" class="btn btn-primary">Gửi email thử</button>
    </form>
</div>
<?php $this->endSection(); ?>
