<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between mb-5">
    <h1 class="mb-0">Sản phẩm</h1>
    <a href="/admin/products/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Sản phẩm mới</a>
</div>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th scope="col">Ảnh</th><th scope="col">Tên</th><th scope="col">Slug</th><th scope="col">Giá</th><th scope="col">Tồn kho</th><th scope="col">Trạng thái</th><th scope="col">Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($products as $product): ?>
<tr>
    <td>
        <?php if (!empty($product['image_path'])): ?>
        <img src="/media/<?= $this->e(\basename((string) $product['image_path'])) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:var(--radius-sm);">
        <?php else: ?>
        <span class="text-muted">—</span>
        <?php endif; ?>
    </td>
    <td><?= $this->e((string) $product['name']) ?></td>
    <td><code><?= $this->e((string) $product['slug']) ?></code></td>
    <td><?= $this->e(\number_format((float) $product['price'], 0, ',', '.')) ?> đ</td>
    <td><?= $this->e((string) $product['stock_quantity']) ?></td>
    <td><span class="badge <?= $product['status'] === 'published' ? 'badge-success' : 'badge-neutral' ?>"><?= $product['status'] === 'published' ? 'Đã xuất bản' : 'Bản nháp' ?></span></td>
    <td>
        <div class="table-actions">
        <a href="/admin/products/<?= $this->e((string) $product['id']) ?>/edit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'edit']); ?> Sửa</a>
        <form method="POST" action="/admin/products/<?= $this->e((string) $product['id']) ?>/delete" data-confirm="Xoá sản phẩm này?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($products)): ?>
<tr><td colspan="7" class="empty-state">Chưa có sản phẩm nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
