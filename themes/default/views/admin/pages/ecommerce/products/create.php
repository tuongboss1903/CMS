<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Sản phẩm mới</h1>
<div class="card" style="max-width: 640px;">
<form method="POST" action="/admin/products">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field">
        <label for="name">Tên sản phẩm</label>
        <input type="text" id="name" name="name" value="<?= $this->e((string) ($old['name'] ?? '')) ?>" required>
        <?php foreach ($errors['name'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" value="<?= $this->e((string) ($old['slug'] ?? '')) ?>" required>
        <?php foreach ($errors['slug'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="description">Mô tả</label>
        <textarea id="description" name="description" rows="4"><?= $this->e((string) ($old['description'] ?? '')) ?></textarea>
    </div>
    <div class="field">
        <label for="category">Danh mục</label>
        <input type="text" id="category" name="category" value="<?= $this->e((string) ($old['category'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="price">Giá</label>
        <input type="number" step="0.01" id="price" name="price" value="<?= $this->e((string) ($old['price'] ?? '')) ?>" required>
        <?php foreach ($errors['price'] ?? [] as $error): ?><p class="field-error"><?= $this->e($error) ?></p><?php endforeach; ?>
    </div>
    <div class="field">
        <label for="sku">SKU</label>
        <input type="text" id="sku" name="sku" value="<?= $this->e((string) ($old['sku'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="stock_quantity">Tồn kho</label>
        <input type="number" id="stock_quantity" name="stock_quantity" value="<?= $this->e((string) ($old['stock_quantity'] ?? '0')) ?>">
    </div>
    <div class="field">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
            <option value="draft" <?= ($old['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Bản nháp</option>
            <option value="published" <?= ($old['status'] ?? '') === 'published' ? 'selected' : '' ?>>Đã xuất bản</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Lưu</button>
</form>
</div>
<?php $this->endSection(); ?>
