<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1>Gio hang</h1>
<?php if (!empty($flash_success)): ?>
<div class="alert alert-success"><?= $this->e((string) $flash_success) ?></div>
<?php endif; ?>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>San pham</th><th>Don gia</th><th>So luong</th><th>Thanh tien</th><th></th></tr></thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr>
    <td><?= $this->e((string) $item['name']) ?></td>
    <td><?= $this->e((string) $item['price']) ?></td>
    <td><?= $this->e((string) $item['quantity']) ?></td>
    <td><?= $this->e((string) ($item['price'] * $item['quantity'])) ?></td>
    <td>
    <form method="POST" action="/cart/remove">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
        <input type="hidden" name="product_id" value="<?= $this->e((string) $item['product_id']) ?>">
        <?php if ($item['variant_id'] !== null): ?>
        <input type="hidden" name="variant_id" value="<?= $this->e((string) $item['variant_id']) ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
    </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($items)): ?>
<tr><td colspan="5" class="empty-state">Gio hang dang trong.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<p><strong>Tong cong: <?= $this->e((string) $total) ?></strong></p>
<?php if (!empty($items)): ?>
<a href="/checkout" class="btn btn-primary">Tien hanh dat hang</a>
<?php endif; ?>
</div>
</div>
<?php $this->endSection(); ?>
