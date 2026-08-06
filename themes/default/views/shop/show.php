<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<div class="breadcrumb">
    <a href="/shop">Sản phẩm</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current"><?= $this->e((string) $product['name']) ?></span>
</div>

<?php $inStock = (int) ($product['stock_quantity'] ?? 0) > 0; ?>
<div class="product-detail">
    <?php if (!empty($product['image_path'])): ?>
    <div class="product-detail-visual product-detail-visual--image">
        <img src="/media/<?= $this->e(\basename((string) $product['image_path'])) ?>" alt="<?= $this->e((string) $product['name']) ?>">
    </div>
    <?php else: ?>
    <div class="product-detail-visual" aria-hidden="true"><?= $this->e(\mb_strtoupper(\mb_substr((string) $product['name'], 0, 1))) ?></div>
    <?php endif; ?>
    <div>
        <div class="product-detail-meta">
        <?php if (!empty($product['category'])): ?>
        <span class="badge badge-neutral"><?= $this->e((string) $product['category']) ?></span>
        <?php endif; ?>
        <span class="badge <?= $inStock ? 'badge-success' : 'badge-danger' ?>"><?= $inStock ? 'Còn hàng' : 'Hết hàng' ?></span>
        </div>
        <h1 class="mb-0"><?= $this->e((string) $product['name']) ?></h1>
        <div class="product-detail-price"><?= $this->e(\number_format((float) $product['price'], 0, ',', '.')) ?> đ</div>
        <p><?= \nl2br($this->e((string) ($product['description'] ?? ''))) ?></p>

        <form method="POST" action="/cart/add">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
            <input type="hidden" name="product_id" value="<?= $this->e((string) $product['id']) ?>">
            <?php if (!empty($variants)): ?>
            <div class="field">
                <label for="variant_id">Chọn biến thể</label>
                <select id="variant_id" name="variant_id">
                <?php foreach ($variants as $variant): ?>
                    <option value="<?= $this->e((string) $variant['id']) ?>"><?= $this->e((string) $variant['name']) ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="field">
                <label for="quantity">Số lượng</label>
                <div class="qty-stepper">
                    <button type="button" data-qty-decrease aria-label="Giảm số lượng">&minus;</button>
                    <input type="number" id="quantity" name="quantity" value="1" min="1">
                    <button type="button" data-qty-increase aria-label="Tăng số lượng">&plus;</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"<?= $inStock ? '' : ' disabled' ?>>Thêm vào giỏ</button>
        </form>

        <div class="trust-badges">
            <span>&#10003; Đổi trả trong 7 ngày</span>
            <span>&#10003; Giao hàng toàn quốc</span>
            <span>&#10003; Thanh toán khi nhận hàng</span>
        </div>
    </div>
</div>
</div>
</div>
<?php $this->endSection(); ?>
