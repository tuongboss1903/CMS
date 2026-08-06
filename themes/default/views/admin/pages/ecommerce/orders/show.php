<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Đơn hàng <?= $this->e((string) $order['order_number']) ?></h1>

<?php
$orderStatusLabels = [
    'pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý', 'shipped' => 'Đang giao',
    'completed' => 'Hoàn tất', 'cancelled' => 'Đã huỷ',
];
$paymentStatusLabels = ['completed' => 'Thành công', 'failed' => 'Thất bại', 'pending' => 'Chờ xử lý'];
$orderStatusDotClass = [
    'pending' => 'status-dot--draft', 'processing' => 'status-dot--active', 'shipped' => 'status-dot--active',
    'completed' => 'status-dot--published', 'cancelled' => 'status-dot--archived',
];
?>
<div class="card mb-5">
    <p><strong>Khách hàng:</strong> <?= $this->e((string) $order['guest_name']) ?> (<?= $this->e((string) $order['guest_email']) ?>)</p>
    <p><strong>Địa chỉ giao hàng:</strong> <?= $this->e((string) ($order['shipping_address'] ?? '-')) ?></p>
    <p><strong>Trạng thái:</strong> <span class="status-dot <?= $this->e($orderStatusDotClass[$order['status']] ?? 'status-dot--draft') ?>"><?= $this->e($orderStatusLabels[$order['status']] ?? (string) $order['status']) ?></span></p>
    <p><strong>Tổng tiền:</strong> <span style="font-family:var(--font-mono);font-weight:600;font-size:var(--text-lg);"><?= $this->e(\number_format((float) $order['total_amount'], 0, ',', '.')) ?> đ</span></p>

    <?php
    $transitions = [
        'pending' => ['processing' => 'Xác nhận xử lý', 'cancelled' => 'Huỷ đơn'],
        'processing' => ['shipped' => 'Đã giao vận chuyển', 'cancelled' => 'Huỷ đơn'],
        'shipped' => ['completed' => 'Hoàn tất'],
    ];
$available = $transitions[$order['status']] ?? [];
?>
    <?php foreach ($available as $nextStatus => $label): ?>
    <form method="POST" action="/admin/orders/<?= $this->e((string) $order['id']) ?>/status" style="display:inline;">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <input type="hidden" name="status" value="<?= $this->e($nextStatus) ?>">
        <button type="submit" class="btn <?= $nextStatus === 'cancelled' ? 'btn-danger' : 'btn-primary' ?> btn-sm"><?= $this->e($label) ?></button>
    </form>
    <?php endforeach; ?>
</div>

<div class="table-wrap table-wrap--flat">
<table class="data-table">
<thead><tr><th scope="col">Sản phẩm</th><th scope="col" style="text-align:right;">Đơn giá</th><th scope="col" style="text-align:right;">Số lượng</th><th scope="col" style="text-align:right;">Thành tiền</th></tr></thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr>
    <td><?= $this->e((string) $item['product_name_snapshot']) ?></td>
    <td style="text-align:right;font-family:var(--font-mono);"><?= $this->e(\number_format((float) $item['unit_price'], 0, ',', '.')) ?> đ</td>
    <td style="text-align:right;font-family:var(--font-mono);"><?= $this->e((string) $item['quantity']) ?></td>
    <td style="text-align:right;font-family:var(--font-mono);"><?= $this->e(\number_format((float) $item['subtotal'], 0, ',', '.')) ?> đ</td>
</tr>
<?php endforeach; ?>
<tr>
    <td colspan="3" style="text-align:right;font-weight:600;border-top:1px solid var(--color-border);">Tổng cộng</td>
    <td style="text-align:right;font-family:var(--font-mono);font-weight:600;border-top:1px solid var(--color-border);"><?= $this->e(\number_format((float) $order['total_amount'], 0, ',', '.')) ?> đ</td>
</tr>
</tbody>
</table>
</div>

<div class="card mt-5">
    <h2 style="font-size:16px;">Lịch sử trạng thái</h2>
    <ol class="order-timeline">
        <li class="order-timeline-item">
            <div class="order-timeline-text">Đơn hàng được tạo</div>
            <div class="order-timeline-time"><?= $this->e((string) $order['created_at']) ?></div>
        </li>
        <?php if (!empty($order['updated_at']) && $order['updated_at'] !== $order['created_at']): ?>
        <li class="order-timeline-item">
            <div class="order-timeline-text">Trạng thái hiện tại: <?= $this->e($orderStatusLabels[$order['status']] ?? (string) $order['status']) ?></div>
            <div class="order-timeline-time"><?= $this->e((string) $order['updated_at']) ?></div>
        </li>
        <?php endif; ?>
    </ol>
</div>

<div class="card mt-5">
    <h2 style="font-size:16px;">Lịch sử thanh toán</h2>
    <div class="table-wrap table-wrap--flat">
    <table class="data-table">
    <thead><tr><th scope="col">Cổng thanh toán</th><th scope="col">Trạng thái</th><th scope="col">Số tiền</th><th scope="col">Mã giao dịch</th><th scope="col">Thời gian</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $payment): ?>
    <tr>
        <td><?= $this->e((string) $payment['driver']) ?></td>
        <td><span class="badge <?= $payment['status'] === 'completed' ? 'badge-success' : ($payment['status'] === 'failed' ? 'badge-danger' : 'badge-neutral') ?>"><?= $this->e($paymentStatusLabels[$payment['status']] ?? (string) $payment['status']) ?></span></td>
        <td><?= $this->e(\number_format((float) $payment['amount'], 0, ',', '.')) ?> đ</td>
        <td><code><?= $this->e((string) ($payment['transaction_ref'] ?? '-')) ?></code></td>
        <td class="text-muted"><?= $this->e((string) $payment['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($payments)): ?>
    <tr><td colspan="5" class="empty-state">Chưa có lần thanh toán nào.</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
    </div>
</div>
<?php $this->endSection(); ?>
