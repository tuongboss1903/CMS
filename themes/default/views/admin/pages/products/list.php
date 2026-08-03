<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">San pham</h1>
    <a href="/admin/products/create" class="btn btn-primary">+ San pham moi</a>
</div>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Ten</th><th>Slug</th><th>Gia</th><th>Ton kho</th><th>Trang thai</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($products as $product): ?>
<tr>
    <td><?= $this->e((string) $product['name']) ?></td>
    <td><code><?= $this->e((string) $product['slug']) ?></code></td>
    <td><?= $this->e((string) $product['price']) ?></td>
    <td><?= $this->e((string) $product['stock_quantity']) ?></td>
    <td><span class="badge <?= $product['status'] === 'published' ? 'badge-success' : 'badge-neutral' ?>"><?= $this->e((string) $product['status']) ?></span></td>
    <td>
        <div class="table-actions">
        <a href="/admin/products/<?= $this->e((string) $product['id']) ?>/edit" class="btn btn-secondary btn-sm">Sua</a>
        <form method="POST" action="/admin/products/<?= $this->e((string) $product['id']) ?>/delete" data-confirm="Xoa san pham nay?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($products)): ?>
<tr><td colspan="6" class="empty-state">Chua co san pham nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
