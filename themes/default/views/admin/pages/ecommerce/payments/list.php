<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Giao dịch thanh toán</h1>

<?php
$statusLabels = ['pending' => 'Chờ xử lý', 'completed' => 'Thành công', 'failed' => 'Thất bại'];
$statusDotClass = ['pending' => 'status-dot--draft', 'completed' => 'status-dot--published', 'failed' => 'status-dot--archived'];
$driverLabels = ['cod' => 'COD', 'momo' => 'MoMo', 'vnpay' => 'VNPay'];
?>

<div class="table-filter-tabs">
    <a href="/admin/payments" class="btn <?= $status_filter === '' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tất cả</a>
    <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
    <a href="/admin/payments?status=<?= $this->e($statusKey) ?>" class="btn <?= $status_filter === $statusKey ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $this->e($statusLabel) ?></a>
    <?php endforeach; ?>
</div>

<div class="table-wrap table-wrap--flat">
<table class="data-table">
<thead>
<tr><th scope="col">Đơn hàng</th><th scope="col">Khách hàng</th><th scope="col">Cổng</th><th scope="col">Số tiền</th><th scope="col">Mã giao dịch</th><th scope="col">Trạng thái</th><th scope="col">Thời gian</th></tr>
</thead>
<tbody>
<?php foreach ($payments as $payment): ?>
<tr>
    <td><a href="/admin/orders/<?= $this->e((string) $payment['order_id']) ?>" class="row-title-link"><code><?= $this->e((string) $payment['order_number']) ?></code></a></td>
    <td><?= $this->e((string) $payment['guest_name']) ?></td>
    <td><?= $this->e($driverLabels[$payment['driver']] ?? (string) $payment['driver']) ?></td>
    <td style="font-family:var(--font-mono);"><?= $this->e(\number_format((float) $payment['amount'], 0, ',', '.')) ?> đ</td>
    <td><code><?= $this->e((string) ($payment['transaction_ref'] ?? '—')) ?></code></td>
    <td><span class="status-dot <?= $this->e($statusDotClass[$payment['status']] ?? 'status-dot--draft') ?>"><?= $this->e($statusLabels[$payment['status']] ?? (string) $payment['status']) ?></span></td>
    <td class="text-muted"><?= $this->e((string) $payment['created_at']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($payments)): ?>
<tr><td colspan="7" class="empty-state">Chưa có giao dịch thanh toán nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
