<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Comments</h1>

<div class="table-filter-tabs">
    <a href="/admin/comments?status=pending" class="btn <?= $status === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Cho duyet</a>
    <a href="/admin/comments?status=approved" class="btn <?= $status === 'approved' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Da duyet</a>
    <a href="/admin/comments?status=rejected" class="btn <?= $status === 'rejected' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Da tu choi</a>
</div>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Nguoi gui</th><th>Trang</th><th>Noi dung</th><th>Thoi gian</th><th>Thao tac</th></tr>
</thead>
<tbody>
<?php foreach ($comments as $comment): ?>
<tr>
    <td><?= $this->e((string) $comment['guest_name']) ?><br><span class="text-muted"><?= $this->e((string) $comment['guest_email']) ?></span></td>
    <td><?= $this->e((string) $comment['page_title']) ?></td>
    <td><?= $this->e((string) $comment['body']) ?></td>
    <td class="text-muted"><?= $this->e((string) $comment['created_at']) ?></td>
    <td>
    <?php if ($comment['status'] !== 'approved'): ?>
    <form method="POST" action="/admin/comments/<?= $this->e((string) $comment['id']) ?>/approve" style="display:inline;">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <button type="submit" class="btn btn-primary btn-sm">Duyet</button>
    </form>
    <?php endif; ?>
    <?php if ($comment['status'] !== 'rejected'): ?>
    <form method="POST" action="/admin/comments/<?= $this->e((string) $comment['id']) ?>/reject" style="display:inline;">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <button type="submit" class="btn btn-secondary btn-sm">Tu choi</button>
    </form>
    <?php endif; ?>
    <form method="POST" action="/admin/comments/<?= $this->e((string) $comment['id']) ?>/delete" style="display:inline;" data-confirm="Xoa binh luan nay?">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
    </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($comments)): ?>
<tr><td colspan="5" class="empty-state">Khong co binh luan nao o trang thai nay.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
