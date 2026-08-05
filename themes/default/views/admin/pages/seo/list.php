<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quản lý SEO</h1>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th scope="col">Tiêu đề</th><th scope="col">Slug</th><th scope="col">Trạng thái SEO</th><th scope="col">Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($pages as $page): ?>
<tr>
    <td><?= $this->e($page['title']) ?></td>
    <td><code><?= $this->e($page['slug']) ?></code></td>
    <td>
        <?php if ((int) $page['has_seo_meta'] === 1): ?>
        <span class="badge badge-success">Đã cấu hình</span>
        <?php else: ?>
        <span class="badge badge-neutral">Chưa cấu hình</span>
        <?php endif; ?>
    </td>
    <td>
        <a href="/admin/seo/pages/<?= $this->e((string) $page['id']) ?>" class="btn btn-secondary btn-sm">Sửa SEO</a>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($pages)): ?>
<tr><td colspan="4" class="empty-state">Chưa có page nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
