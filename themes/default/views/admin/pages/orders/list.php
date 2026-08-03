<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Don hang</h1>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Ma don</th><th>Khach hang</th><th>Tong tien</th><th>Trang thai</th><th>Thoi gian</th><th></th></tr>
</thead>
<tbody>
<?php foreach ($orders as $order): ?>
<tr>
    <td><code><?= $this->e((string) $order['order_number']) ?></code></td>
    <td><?= $this->e((string) $order['guest_name']) ?><br><span class="text-muted"><?= $this->e((string) $order['guest_email']) ?></span></td>
    <td><?= $this->e((string) $order['total_amount']) ?></td>
    <td><span class="badge badge-neutral"><?= $this->e((string) $order['status']) ?></span></td>
    <td class="text-muted"><?= $this->e((string) $order['created_at']) ?></td>
    <td><a href="/admin/orders/<?= $this->e((string) $order['id']) ?>" class="btn btn-secondary btn-sm">Xem</a></td>
</tr>
<?php endforeach; ?>
<?php if (empty($orders)): ?>
<tr><td colspan="6" class="empty-state">Chua co don hang nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
