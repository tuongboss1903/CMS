<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1><?= $this->e((string) $product['name']) ?></h1>
<p><?= \nl2br($this->e((string) ($product['description'] ?? ''))) ?></p>
<p><strong><?= $this->e((string) $product['price']) ?></strong></p>
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
        <input type="number" id="quantity" name="quantity" value="1" min="1">
    </div>
    <button type="submit" class="btn btn-primary">Them vao gio</button>
</form>
</div>
</div>
<?php $this->endSection(); ?>
