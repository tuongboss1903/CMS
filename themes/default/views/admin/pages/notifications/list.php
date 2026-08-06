<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between mb-5">
    <h1 class="mb-0">Thông báo</h1>
    <form method="POST" action="/admin/notifications/read-all">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <button type="submit" class="btn btn-secondary"><?php $this->include('admin.partials.icon', ['name' => 'check']); ?> Đánh dấu tất cả đã đọc</button>
    </form>
</div>
<p class="text-muted"><?= $this->e((string) $total) ?> thông báo.</p>

<?php if (empty($notifications)): ?>
<?php $this->include('admin.partials.empty_state', [
    'icon' => 'notification',
    'title' => 'Chưa có thông báo nào',
    'description' => 'Thông báo sẽ xuất hiện ở đây khi có bình luận mới, đăng ký người dùng, hoặc sự kiện hệ thống cần bạn chú ý.',
]); ?>
<?php else: ?>
<div class="table-wrap table-wrap--flat">
<table class="data-table">
<thead>
<tr><th scope="col">Trạng thái</th><th scope="col">Tiêu đề</th><th scope="col">Nội dung</th><th scope="col">Thời gian</th><th scope="col">Hành động</th></tr>
</thead>
<tbody>
<?php foreach ($notifications as $notification): ?>
<tr>
    <td data-field="status">
        <span class="status-dot <?= empty($notification['read_at']) ? 'status-dot--active' : 'status-dot--draft' ?>"><?= empty($notification['read_at']) ? 'Chưa đọc' : 'Đã đọc' ?></span>
    </td>
    <td><?= $this->e((string) $notification['title']) ?></td>
    <td class="text-muted"><?= $this->e((string) $notification['body']) ?></td>
    <td class="text-muted"><?= $this->e((string) $notification['created_at']) ?></td>
    <td>
        <?php if (empty($notification['read_at'])): ?>
        <form method="POST" action="/admin/notifications/<?= $this->e((string) $notification['id']) ?>/read">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Đánh dấu đã đọc</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php $this->include('admin.partials.pagination', [
    'page' => $page,
    'total_pages' => $total_pages,
    'base_url' => '/admin/notifications?',
]); ?>
<?php $this->endSection(); ?>
