<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Quản lý Trang nội dung</h1>
    <a href="/admin/pages/create" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo trang mới</a>
</div>

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

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Tiêu đề</th><th>Slug</th><th>Trạng thái</th><th>Trang chủ</th><th>Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($pages as $page): ?>
<tr>
    <td><?= $this->e($page['title']) ?></td>
    <td><code><?= $this->e($page['slug']) ?></code></td>
    <td><span class="badge <?= $page['status'] === 'published' ? 'badge-success' : 'badge-neutral' ?>"><?= $page['status'] === 'published' ? 'Đã xuất bản' : 'Bản nháp' ?></span></td>
    <td><?php if ((int) $page['is_homepage'] === 1): ?><span class="badge badge-warning">Trang chủ</span><?php endif; ?></td>
    <td>
        <div class="table-actions">
        <a href="/admin/pages/<?= $this->e((string) $page['id']) ?>/edit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'edit']); ?> Sửa</a>

        <?php if ($page['status'] === 'published'): ?>
        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/publish">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <input type="hidden" name="status" value="draft">
            <button type="submit" class="btn btn-secondary btn-sm">Chuyển về nháp</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/publish">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <input type="hidden" name="status" value="published">
            <button type="submit" class="btn btn-primary btn-sm">Xuất bản</button>
        </form>
        <?php endif; ?>

        <?php if ((int) $page['is_homepage'] !== 1): ?>
        <form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>/homepage">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Đặt làm trang chủ</button>
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
<tr><td colspan="5" class="empty-state">Chưa có trang nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
