<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Bình luận</h1>

<div class="table-filter-tabs">
    <a href="/admin/comments?status=pending" class="btn <?= $status === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Chờ duyệt</a>
    <a href="/admin/comments?status=approved" class="btn <?= $status === 'approved' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Đã duyệt</a>
    <a href="/admin/comments?status=rejected" class="btn <?= $status === 'rejected' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Đã từ chối</a>
</div>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th scope="col">Người gửi</th><th scope="col">Trang</th><th scope="col">Nội dung</th><th scope="col">Thời gian</th><th scope="col">Thao tác</th></tr>
</thead>
<tbody>
<?php foreach ($comments as $comment): ?>
<tr>
    <td><?= $this->e((string) $comment['guest_name']) ?><br><span class="text-muted"><?= $this->e((string) $comment['guest_email']) ?></span></td>
    <td><?= $this->e((string) $comment['page_title']) ?></td>
    <td><?= $this->e((string) $comment['body']) ?></td>
    <td class="text-muted"><?= $this->e((string) $comment['created_at']) ?></td>
    <td>
    <div class="table-actions">
    <?php if ($comment['status'] !== 'approved'): ?>
    <form method="POST" action="/admin/comments/<?= $this->e((string) $comment['id']) ?>/approve">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <button type="submit" class="btn btn-primary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'check']); ?> Duyệt</button>
    </form>
    <?php endif; ?>
    <?php if ($comment['status'] !== 'rejected'): ?>
    <form method="POST" action="/admin/comments/<?= $this->e((string) $comment['id']) ?>/reject">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <button type="submit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'x']); ?> Từ chối</button>
    </form>
    <?php endif; ?>
    <form method="POST" action="/admin/comments/<?= $this->e((string) $comment['id']) ?>/delete" data-confirm="Xoá bình luận này?">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
    </form>
    </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($comments)): ?>
<tr><td colspan="5" class="empty-state">Không có bình luận nào ở trạng thái này.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
