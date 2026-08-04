<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1>San pham</h1>
<?php if (!empty($cart_error)): ?>
<div class="alert alert-danger"><?= $this->e((string) $cart_error) ?></div>
<?php endif; ?>

<div class="product-grid">
<?php foreach ($products as $product): ?>
<?php $inStock = (int) ($product['stock_quantity'] ?? 0) > 0; ?>
<div class="product-card">
    <a href="/shop/<?= $this->e((string) $product['slug']) ?>" class="product-card-visual" aria-hidden="true"><?= $this->e(\mb_strtoupper(\mb_substr((string) $product['name'], 0, 1))) ?></a>
    <div class="product-card-body">
        <?php if (!empty($product['category'])): ?>
        <div class="product-card-category"><?= $this->e((string) $product['category']) ?></div>
        <?php endif; ?>
        <a href="/shop/<?= $this->e((string) $product['slug']) ?>" class="product-card-name"><?= $this->e((string) $product['name']) ?></a>
        <div class="product-card-price"><?= $this->e(\number_format((float) $product['price'], 0, ',', '.')) ?> d</div>
        <?php if (!$inStock): ?>
        <span class="badge badge-danger">Het hang</span>
        <?php endif; ?>
    </div>
    <div class="product-card-footer">
        <form method="POST" action="/cart/add">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
            <input type="hidden" name="product_id" value="<?= $this->e((string) $product['id']) ?>">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" class="btn btn-primary btn-sm"<?= $inStock ? '' : ' disabled' ?>>Them vao gio</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($products)): ?>
<p class="empty-state">Chua co san pham nao.</p>
<?php endif; ?>
</div>
</div>
</div>
<?php $this->endSection(); ?>
