<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quan ly Menu</h1>

<div class="card" style="max-width: 480px; margin-bottom: var(--space-5);">
    <h2 class="mb-0" style="font-size:16px;">Tao Menu moi</h2>
    <form method="POST" action="/admin/menus">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <div class="field">
            <label for="name">Ten Menu</label>
            <input type="text" id="name" name="name">
        </div>
        <div class="field">
            <label for="location_key">Location key</label>
            <input type="text" id="location_key" name="location_key" placeholder="header">
        </div>
        <button type="submit" class="btn btn-primary">Tao Menu</button>
    </form>
</div>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Ten Menu</th><th>Location key</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($menus as $menu): ?>
<tr>
    <td><?= $this->e($menu['name']) ?></td>
    <td><code><?= $this->e($menu['location_key']) ?></code></td>
    <td>
        <div class="table-actions">
        <a href="/admin/menus/<?= $this->e((string) $menu['id']) ?>" class="btn btn-secondary btn-sm">Quan ly cau truc</a>
        <form method="POST" action="/admin/menus/<?= $this->e((string) $menu['id']) ?>/delete" data-confirm="Xac nhan xoa menu nay va toan bo item ben trong?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($menus)): ?>
<tr><td colspan="3" class="empty-state">Chua co menu nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
