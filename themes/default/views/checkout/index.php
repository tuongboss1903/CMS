<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1>Đặt hàng</h1>

<div class="commerce-layout">
    <form method="POST" action="/checkout">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
        <div class="card" style="margin-bottom: var(--space-5);">
        <h2 style="margin-top:0;">Thông tin giao hàng</h2>
        <div class="field">
            <label for="guest_name">Họ tên</label>
            <input type="text" id="guest_name" name="guest_name" value="<?= $this->e((string) ($old['guest_name'] ?? '')) ?>" required>
            <?php foreach ($errors['guest_name'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
        </div>
        <div class="field">
            <label for="guest_email">Email</label>
            <input type="email" id="guest_email" name="guest_email" value="<?= $this->e((string) ($old['guest_email'] ?? '')) ?>" required>
            <?php foreach ($errors['guest_email'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
        </div>
        <div class="field">
            <label for="shipping_address">Địa chỉ giao hàng</label>
            <textarea id="shipping_address" name="shipping_address" rows="3"><?= $this->e((string) ($old['shipping_address'] ?? '')) ?></textarea>
        </div>
        </div>

        <div class="card">
        <h2 style="margin-top:0;">Phương thức thanh toán</h2>
        <?php $selectedMethod = (string) ($old['payment_method'] ?? 'cod'); ?>
        <div class="payment-method-options">
            <label class="payment-method-option">
                <input type="radio" name="payment_method" value="cod" <?= $selectedMethod === 'cod' ? 'checked' : '' ?>>
                <span>Thanh toán khi nhận hàng (COD)</span>
            </label>
            <label class="payment-method-option">
                <input type="radio" name="payment_method" value="momo" <?= $selectedMethod === 'momo' ? 'checked' : '' ?>>
                <span>Ví MoMo</span>
            </label>
            <label class="payment-method-option">
                <input type="radio" name="payment_method" value="vnpay" <?= $selectedMethod === 'vnpay' ? 'checked' : '' ?>>
                <span>VNPay</span>
            </label>
        </div>
        </div>

        <?php if (!empty($errors['cart'])): ?>
        <div class="alert alert-danger" style="margin-top: var(--space-4);"><?= $this->e($errors['cart'][0]) ?></div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-block" style="margin-top: var(--space-5);">Xác nhận đặt hàng</button>
    </form>

    <aside class="order-summary">
        <h2>Đơn hàng của bạn</h2>
        <?php foreach ($items as $item): ?>
        <div class="order-summary-row">
            <span><?= $this->e((string) $item['name']) ?> &times; <?= $this->e((string) $item['quantity']) ?></span>
            <span><?= $this->e(\number_format(((float) $item['price']) * ((int) $item['quantity']), 0, ',', '.')) ?> đ</span>
        </div>
        <?php endforeach; ?>
        <div class="order-summary-total"><span>Tổng cộng</span><span><?= $this->e(\number_format((float) $total, 0, ',', '.')) ?> đ</span></div>
    </aside>
</div>
</div>
</div>
<?php $this->endSection(); ?>
