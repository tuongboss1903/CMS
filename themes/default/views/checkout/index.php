<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1>Dat hang</h1>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>San pham</th><th>So luong</th><th>Thanh tien</th></tr></thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr>
    <td><?= $this->e((string) $item['name']) ?></td>
    <td><?= $this->e((string) $item['quantity']) ?></td>
    <td><?= $this->e((string) ($item['price'] * $item['quantity'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<p><strong>Tong cong: <?= $this->e((string) $total) ?></strong></p>

<form method="POST" action="/checkout">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <div class="field">
        <label for="guest_name">Ho ten</label>
        <input type="text" id="guest_name" name="guest_name" value="<?= $this->e((string) ($old['guest_name'] ?? '')) ?>" required>
        <?php foreach ($errors['guest_name'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="guest_email">Email</label>
        <input type="email" id="guest_email" name="guest_email" value="<?= $this->e((string) ($old['guest_email'] ?? '')) ?>" required>
        <?php foreach ($errors['guest_email'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="shipping_address">Dia chi giao hang</label>
        <textarea id="shipping_address" name="shipping_address" rows="3"><?= $this->e((string) ($old['shipping_address'] ?? '')) ?></textarea>
    </div>
    <div class="field">
        <label for="payment_method">Phuong thuc thanh toan</label>
        <?php $selectedMethod = (string) ($old['payment_method'] ?? 'cod'); ?>
        <select id="payment_method" name="payment_method">
            <option value="cod" <?= $selectedMethod === 'cod' ? 'selected' : '' ?>>Thanh toan khi nhan hang (COD)</option>
            <option value="momo" <?= $selectedMethod === 'momo' ? 'selected' : '' ?>>Vi MoMo</option>
            <option value="vnpay" <?= $selectedMethod === 'vnpay' ? 'selected' : '' ?>>VNPay</option>
        </select>
    </div>
    <?php if (!empty($errors['cart'])): ?>
    <div class="alert alert-danger"><?= $this->e($errors['cart'][0]) ?></div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">Xac nhan dat hang</button>
</form>
</div>
</div>
<?php $this->endSection(); ?>
