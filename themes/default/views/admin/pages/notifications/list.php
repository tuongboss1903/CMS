<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Thong bao</h1>
    <form method="POST" action="/admin/notifications/read-all">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <button type="submit" class="btn btn-secondary">Danh dau tat ca da doc</button>
    </form>
</div>
<p class="text-muted"><?= $this->e((string) $total) ?> thong bao.</p>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Trang thai</th><th>Tieu de</th><th>Noi dung</th><th>Thoi gian</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($notifications as $notification): ?>
<tr>
    <td data-field="status">
        <span class="badge <?= empty($notification['read_at']) ? 'badge-danger' : 'badge-secondary' ?>"><?= empty($notification['read_at']) ? 'Chua doc' : 'Da doc' ?></span>
    </td>
    <td><?= $this->e((string) $notification['title']) ?></td>
    <td class="text-muted"><?= $this->e((string) $notification['body']) ?></td>
    <td class="text-muted"><?= $this->e((string) $notification['created_at']) ?></td>
    <td>
        <?php if (empty($notification['read_at'])): ?>
        <form method="POST" action="/admin/notifications/<?= $this->e((string) $notification['id']) ?>/read">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Danh dau da doc</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($notifications)): ?>
<tr><td colspan="5" class="empty-state">Chua co thong bao nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<?php $this->include('admin.partials.pagination', [
    'page' => $page,
    'total_pages' => $total_pages,
    'base_url' => '/admin/notifications?',
]); ?>
<?php $this->endSection(); ?>
