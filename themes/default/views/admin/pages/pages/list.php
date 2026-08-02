<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Quan ly Page</h1>
    <a href="/admin/pages/create" class="btn btn-primary">+ Tao page moi</a>
</div>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Tieu de</th><th>Slug</th><th>Trang thai</th><th>Trang chu</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($pages as $page): ?>
<tr>
    <td><?= $this->e($page['title']) ?></td>
    <td><code><?= $this->e($page['slug']) ?></code></td>
    <td><span class="badge <?= $page['status'] === 'published' ? 'badge-success' : 'badge-neutral' ?>"><?= $this->e($page['status']) ?></span></td>
    <td><?php if ((int) $page['is_homepage'] === 1): ?><span class="badge badge-warning">Trang chu</span><?php endif; ?></td>
    <td>
        <div class="table-actions">
        <a href="/admin/pages/<?= $this->e((string) $page['id']) ?>/edit" class="btn btn-secondary btn-sm">Sua</a>

        <?php if ($page['status'] === 'published'): ?>
        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/publish">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <input type="hidden" name="status" value="draft">
            <button type="submit" class="btn btn-secondary btn-sm">Chuyen ve nhap</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/publish">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <input type="hidden" name="status" value="published">
            <button type="submit" class="btn btn-primary btn-sm">Xuat ban</button>
        </form>
        <?php endif; ?>

        <?php if ((int) $page['is_homepage'] !== 1): ?>
        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/homepage">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Dat trang chu</button>
        </form>
        <?php endif; ?>

        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/delete" data-confirm="Xac nhan xoa page nay?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($pages)): ?>
<tr><td colspan="5" class="empty-state">Chua co page nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
