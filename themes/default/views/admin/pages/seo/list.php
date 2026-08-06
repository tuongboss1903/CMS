<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quản lý SEO</h1>

<?php if (isset($stats)): ?>
<div class="stat-grid mb-5">
    <div class="stat-card">
        <div class="stat-label">Tổng số trang</div>
        <div class="stat-value"><?= $this->e((string) $stats['total']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Đã cấu hình SEO</div>
        <div class="stat-value"><?= $this->e((string) $stats['configured']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Chưa cấu hình</div>
        <div class="stat-value"><?= $this->e((string) $stats['missing']) ?></div>
    </div>
</div>
<?php endif; ?>

<div class="table-wrap table-wrap--flat">
<table class="data-table">
<thead>
<tr><th scope="col">Tiêu đề</th><th scope="col">Slug</th><th scope="col">Trạng thái SEO</th><th scope="col">Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($pages as $page): ?>
<tr>
    <td><a href="/admin/seo/pages/<?= $this->e((string) $page['id']) ?>" class="row-title-link"><?= $this->e($page['title']) ?></a></td>
    <td><code><?= $this->e($page['slug']) ?></code></td>
    <td>
        <?php if ((int) $page['has_seo_meta'] === 1): ?>
        <span class="status-dot status-dot--published">Đã cấu hình</span>
        <?php else: ?>
        <span class="status-dot status-dot--draft">Chưa cấu hình</span>
        <?php endif; ?>
    </td>
    <td>
        <a href="/admin/seo/pages/<?= $this->e((string) $page['id']) ?>" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'seo']); ?> Sửa SEO</a>
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
