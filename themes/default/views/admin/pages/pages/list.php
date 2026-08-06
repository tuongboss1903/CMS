<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between mb-5">
    <h1 class="mb-0">Quản lý Trang nội dung</h1>
    <a href="/admin/pages/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo trang mới</a>
</div>

<?php if (isset($stats)): ?>
<div class="stat-grid mb-5">
    <div class="stat-card">
        <div class="stat-label">Tổng số trang</div>
        <div class="stat-value"><?= $this->e((string) $stats['total']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Đã xuất bản</div>
        <div class="stat-value"><?= $this->e((string) $stats['published']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Bản nháp</div>
        <div class="stat-value"><?= $this->e((string) $stats['draft']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Lượt xem hôm nay</div>
        <div class="stat-value"><?= $this->e((string) $stats['views_today']) ?></div>
    </div>
</div>
<?php endif; ?>

<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/admin/pages',
    'filter_fields' => [
        ['name' => 'q', 'label' => 'Tìm theo tiêu đề/slug', 'type' => 'text', 'value' => $filters['q'] ?? ''],
        ['name' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'value' => $filters['status'] ?? '', 'options' => [
            ['value' => 'draft', 'label' => 'Bản nháp'],
            ['value' => 'published', 'label' => 'Đã xuất bản'],
        ]],
    ],
]); ?>

<div class="table-wrap table-wrap--flat">
<table class="data-table">
<thead>
<tr><th scope="col">Tiêu đề</th><th scope="col">Slug</th><th scope="col">Trạng thái</th><th scope="col">Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($pages as $page): ?>
<tr>
    <td class="truncate-cell">
        <div class="page-title-cell">
            <a href="/admin/pages/<?= $this->e((string) $page['id']) ?>/edit" class="row-title-link" title="<?= $this->e($page['title']) ?>"><?= $this->e($page['title']) ?></a>
            <?php if ((int) $page['is_homepage'] === 1): ?><span class="badge badge-warning badge-inline"><?php $this->include('admin.partials.icon', ['name' => 'home', 'class' => 'icon icon--badge']); ?> Trang chủ</span><?php endif; ?>
        </div>
    </td>
    <td><code><?= $this->e($page['slug']) ?></code></td>
    <td><span class="status-dot <?= $page['status'] === 'published' ? 'status-dot--published' : 'status-dot--draft' ?>"><?= $page['status'] === 'published' ? 'Đã xuất bản' : 'Bản nháp' ?></span></td>
    <td>
        <div class="table-actions">
        <a href="/admin/pages/<?= $this->e((string) $page['id']) ?>/edit" class="btn btn-secondary btn-sm" aria-label="Sửa trang <?= $this->e($page['title']) ?>"><?php $this->include('admin.partials.icon', ['name' => 'edit']); ?> Sửa</a>

        <?php if ($page['status'] === 'published'): ?>
        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/publish">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <input type="hidden" name="status" value="draft">
            <button type="submit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'x']); ?> Chuyển về nháp</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/publish">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <input type="hidden" name="status" value="published">
            <button type="submit" class="btn btn-primary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'check']); ?> Xuất bản</button>
        </form>
        <?php endif; ?>

        <?php if ((int) $page['is_homepage'] !== 1): ?>
        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/homepage">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'home']); ?> Đặt làm trang chủ</button>
        </form>
        <?php endif; ?>

        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/delete" data-confirm="Xác nhận xoá trang này?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
        </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($pages)): ?>
<tr><td colspan="4" class="empty-state">Chưa có trang nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
