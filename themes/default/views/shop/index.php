<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1>San pham</h1>
<?php if (!empty($cart_error)): ?>
<div class="alert alert-danger"><?= $this->e((string) $cart_error) ?></div>
<?php endif; ?>
<div class="stat-grid">
<?php foreach ($products as $product): ?>
<div class="card">
    <h2 style="font-size:16px; margin-top:0;"><a href="/shop/<?= $this->e((string) $product['slug']) ?>"><?= $this->e((string) $product['name']) ?></a></h2>
    <p class="text-muted"><?= $this->e((string) ($product['category'] ?? '')) ?></p>
    <p><strong><?= $this->e((string) $product['price']) ?></strong></p>
    <form method="POST" action="/cart/add">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
        <input type="hidden" name="product_id" value="<?= $this->e((string) $product['id']) ?>">
        <input type="hidden" name="quantity" value="1">
        <button type="submit" class="btn btn-primary btn-sm">Them vao gio</button>
    </form>
</div>
<?php endforeach; ?>
<?php if (empty($products)): ?>
<p class="empty-state">Chua co san pham nao.</p>
<?php endif; ?>
</div>
</div>
</div>
<?php $this->endSection(); ?>
