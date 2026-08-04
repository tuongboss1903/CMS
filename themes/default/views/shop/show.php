<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<div class="breadcrumb">
    <a href="/shop">San pham</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current"><?= $this->e((string) $product['name']) ?></span>
</div>

<?php $inStock = (int) ($product['stock_quantity'] ?? 0) > 0; ?>
<div class="product-detail">
    <div class="product-detail-visual" aria-hidden="true"><?= $this->e(\mb_strtoupper(\mb_substr((string) $product['name'], 0, 1))) ?></div>
    <div>
        <div class="product-detail-meta">
        <?php if (!empty($product['category'])): ?>
        <span class="badge badge-neutral"><?= $this->e((string) $product['category']) ?></span>
        <?php endif; ?>
        <span class="badge <?= $inStock ? 'badge-success' : 'badge-danger' ?>"><?= $inStock ? 'Con hang' : 'Het hang' ?></span>
        </div>
        <h1 class="mb-0"><?= $this->e((string) $product['name']) ?></h1>
        <div class="product-detail-price"><?= $this->e(\number_format((float) $product['price'], 0, ',', '.')) ?> d</div>
        <p><?= \nl2br($this->e((string) ($product['description'] ?? ''))) ?></p>

        <form method="POST" action="/cart/add">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
            <input type="hidden" name="product_id" value="<?= $this->e((string) $product['id']) ?>">
            <?php if (!empty($variants)): ?>
            <div class="field">
                <label for="variant_id">Chon bien the</label>
                <select id="variant_id" name="variant_id">
                <?php foreach ($variants as $variant): ?>
                    <option value="<?= $this->e((string) $variant['id']) ?>"><?= $this->e((string) $variant['name']) ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="field">
                <label for="quantity">So luong</label>
                <div class="qty-stepper">
                    <button type="button" data-qty-decrease aria-label="Giam so luong">&minus;</button>
                    <input type="number" id="quantity" name="quantity" value="1" min="1">
                    <button type="button" data-qty-increase aria-label="Tang so luong">&plus;</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"<?= $inStock ? '' : ' disabled' ?>>Them vao gio</button>
        </form>

        <div class="trust-badges">
            <span>&#10003; Doi tra trong 7 ngay</span>
            <span>&#10003; Giao hang toan quoc</span>
            <span>&#10003; Thanh toan khi nhan hang</span>
        </div>
    </div>
</div>
</div>
</div>
<?php $this->endSection(); ?>
