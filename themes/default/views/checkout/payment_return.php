<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container" style="max-width: 480px;">
<div class="card" style="text-align: center;">
    <h1>Kết quả thanh toán</h1>
    <?php if ($order !== null): ?>
    <p>Đơn hàng <strong><?= $this->e((string) $order['order_number']) ?></strong> hiện đang ở trạng thái:</p>
    <p><span class="badge badge-neutral"><?= $this->e((string) $order['status']) ?></span></p>
    <p class="text-muted">Trạng thái thanh toán chính thức sẽ được cập nhật tự động khi cổng thanh toán xác nhận xong - vui lòng kiểm tra lại sau ít phút nếu chưa thấy cập nhật.</p>
    <?php else: ?>
    <p>Không tìm thấy thông tin đơn hàng <?= $this->e($order_number) ?>.</p>
    <?php endif; ?>
    <a href="/shop" class="btn btn-secondary">Tiếp tục mua sắm</a>
</div>
</div>
</div>
<?php $this->endSection(); ?>
