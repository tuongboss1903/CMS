<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Đơn hàng</h1>
<?php
$orderStatusLabels = [
    'pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý', 'shipped' => 'Đang giao',
    'completed' => 'Hoàn tất', 'cancelled' => 'Đã huỷ',
];
?>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th scope="col">Mã đơn</th><th scope="col">Khách hàng</th><th scope="col">Tổng tiền</th><th scope="col">Trạng thái</th><th scope="col">Thời gian</th><th scope="col"></th></tr>
</thead>
<tbody>
<?php foreach ($orders as $order): ?>
<tr>
    <td><code><?= $this->e((string) $order['order_number']) ?></code></td>
    <td><?= $this->e((string) $order['guest_name']) ?><br><span class="text-muted"><?= $this->e((string) $order['guest_email']) ?></span></td>
    <td><?= $this->e((string) $order['total_amount']) ?></td>
    <td><span class="badge badge-neutral"><?= $this->e($orderStatusLabels[$order['status']] ?? (string) $order['status']) ?></span></td>
    <td class="text-muted"><?= $this->e((string) $order['created_at']) ?></td>
    <td><a href="/admin/orders/<?= $this->e((string) $order['id']) ?>" class="btn btn-secondary btn-sm">Xem</a></td>
</tr>
<?php endforeach; ?>
<?php if (empty($orders)): ?>
<tr><td colspan="6" class="empty-state">Chưa có đơn hàng nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
