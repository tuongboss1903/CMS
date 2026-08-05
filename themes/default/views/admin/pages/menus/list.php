<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quản lý Menu</h1>

<div class="card mb-5" style="max-width: 480px;">
    <h2 class="mb-0" style="font-size:16px;">Tạo Menu mới</h2>
    <form method="POST" action="/admin/menus">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <div class="field">
            <label for="name">Tên Menu</label>
            <input type="text" id="name" name="name">
        </div>
        <div class="field">
            <label for="location_key">Vị trí hiển thị (location key)</label>
            <input type="text" id="location_key" name="location_key" placeholder="header">
        </div>
        <button type="submit" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo Menu</button>
    </form>
</div>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th scope="col">Tên Menu</th><th scope="col">Vị trí hiển thị</th><th scope="col">Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($menus as $menu): ?>
<tr>
    <td><?= $this->e($menu['name']) ?></td>
    <td><code><?= $this->e($menu['location_key']) ?></code></td>
    <td>
        <div class="table-actions">
        <a href="/admin/menus/<?= $this->e((string) $menu['id']) ?>" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'menu']); ?> Quản lý cấu trúc</a>
        <form method="POST" action="/admin/menus/<?= $this->e((string) $menu['id']) ?>/delete" data-confirm="Xác nhận xoá menu này và toàn bộ mục bên trong?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($menus)): ?>
<tr><td colspan="3" class="empty-state">Chưa có menu nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
