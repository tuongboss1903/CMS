<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quan ly SEO</h1>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th scope="col">Tieu de</th><th scope="col">Slug</th><th scope="col">Trang thai SEO</th><th scope="col">Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($pages as $page): ?>
<tr>
    <td><?= $this->e($page['title']) ?></td>
    <td><code><?= $this->e($page['slug']) ?></code></td>
    <td>
        <?php if ((int) $page['has_seo_meta'] === 1): ?>
        <span class="badge badge-success">Da cau hinh</span>
        <?php else: ?>
        <span class="badge badge-neutral">Chua cau hinh</span>
        <?php endif; ?>
    </td>
    <td>
        <a href="/admin/seo/pages/<?= $this->e((string) $page['id']) ?>" class="btn btn-secondary btn-sm">Sua SEO</a>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($pages)): ?>
<tr><td colspan="4" class="empty-state">Chua co page nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
