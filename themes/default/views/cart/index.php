<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1>Gio hang</h1>
<?php if (!empty($flash_success)): ?>
<div class="alert alert-success"><?= $this->e((string) $flash_success) ?></div>
<?php endif; ?>

<?php if (empty($items)): ?>
<div class="empty-state">
    <p class="mb-0">Gio hang dang trong.</p>
    <a href="/shop" class="btn btn-primary" style="margin-top: var(--space-4);">Tiep tuc mua sam</a>
</div>
<?php else: ?>
<div class="commerce-layout">
    <div>
    <?php foreach ($items as $item): ?>
    <div class="cart-item-row">
        <div class="cart-item-visual" aria-hidden="true"><?= $this->e(\mb_strtoupper(\mb_substr((string) $item['name'], 0, 1))) ?></div>
        <div class="cart-item-info">
            <div class="cart-item-name"><?= $this->e((string) $item['name']) ?></div>
            <div class="cart-item-price"><?= $this->e(\number_format((float) $item['price'], 0, ',', '.')) ?> d &times; <?= $this->e((string) $item['quantity']) ?></div>
        </div>
        <div class="cart-item-line-total"><?= $this->e(\number_format(((float) $item['price']) * ((int) $item['quantity']), 0, ',', '.')) ?> d</div>
        <form method="POST" action="/cart/remove">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
            <input type="hidden" name="product_id" value="<?= $this->e((string) $item['product_id']) ?>">
            <?php if ($item['variant_id'] !== null): ?>
            <input type="hidden" name="variant_id" value="<?= $this->e((string) $item['variant_id']) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
        </form>
    </div>
    <?php endforeach; ?>
    </div>

    <aside class="order-summary">
        <h2>Tom tat don hang</h2>
        <div class="order-summary-row"><span>Tam tinh</span><span><?= $this->e(\number_format((float) $total, 0, ',', '.')) ?> d</span></div>
        <div class="order-summary-total"><span>Tong cong</span><span><?= $this->e(\number_format((float) $total, 0, ',', '.')) ?> d</span></div>
        <a href="/checkout" class="btn btn-primary btn-block" style="margin-top: var(--space-4);">Tien hanh dat hang</a>
        <a href="/shop" class="btn btn-secondary btn-block" style="margin-top: var(--space-2);">Tiep tuc mua sam</a>
    </aside>
</div>
<?php endif; ?>
</div>
</div>
<?php $this->endSection(); ?>
