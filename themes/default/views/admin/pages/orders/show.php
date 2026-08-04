<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Don hang <?= $this->e((string) $order['order_number']) ?></h1>

<div class="card" style="margin-bottom: var(--space-5);">
    <p><strong>Khach hang:</strong> <?= $this->e((string) $order['guest_name']) ?> (<?= $this->e((string) $order['guest_email']) ?>)</p>
    <p><strong>Dia chi giao hang:</strong> <?= $this->e((string) ($order['shipping_address'] ?? '-')) ?></p>
    <p><strong>Trang thai:</strong> <span class="badge badge-neutral"><?= $this->e((string) $order['status']) ?></span></p>
    <p><strong>Tong tien:</strong> <?= $this->e((string) $order['total_amount']) ?></p>

    <?php
    $transitions = [
        'pending' => ['processing' => 'Xac nhan xu ly', 'cancelled' => 'Huy don'],
        'processing' => ['shipped' => 'Da giao van chuyen', 'cancelled' => 'Huy don'],
        'shipped' => ['completed' => 'Hoan tat'],
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

<div class="table-wrap">
<table class="data-table">
<thead><tr><th>San pham</th><th>Don gia</th><th>So luong</th><th>Thanh tien</th></tr></thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr>
    <td><?= $this->e((string) $item['product_name_snapshot']) ?></td>
    <td><?= $this->e((string) $item['unit_price']) ?></td>
    <td><?= $this->e((string) $item['quantity']) ?></td>
    <td><?= $this->e((string) $item['subtotal']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div class="card" style="margin-top: var(--space-5);">
    <h2 style="font-size:16px; margin-top:0;">Lich su thanh toan</h2>
    <div class="table-wrap">
    <table class="data-table">
    <thead><tr><th>Cong thanh toan</th><th>Trang thai</th><th>So tien</th><th>Ma giao dich</th><th>Thoi gian</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $payment): ?>
    <tr>
        <td><?= $this->e((string) $payment['driver']) ?></td>
        <td><span class="badge <?= $payment['status'] === 'completed' ? 'badge-success' : ($payment['status'] === 'failed' ? 'badge-danger' : 'badge-neutral') ?>"><?= $this->e((string) $payment['status']) ?></span></td>
        <td><?= $this->e((string) $payment['amount']) ?></td>
        <td><code><?= $this->e((string) ($payment['transaction_ref'] ?? '-')) ?></code></td>
        <td class="text-muted"><?= $this->e((string) $payment['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($payments)): ?>
    <tr><td colspan="5" class="empty-state">Chua co lan thanh toan nao.</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
    </div>
</div>
<?php $this->endSection(); ?>
