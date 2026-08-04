<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container" style="max-width: 480px;">
<div class="card" style="text-align: center;">
    <h1>Ket qua thanh toan</h1>
    <?php if ($order !== null): ?>
    <p>Don hang <strong><?= $this->e((string) $order['order_number']) ?></strong> hien dang o trang thai:</p>
    <p><span class="badge badge-neutral"><?= $this->e((string) $order['status']) ?></span></p>
    <p class="text-muted">Trang thai thanh toan chinh thuc se duoc cap nhat tu dong khi cong thanh toan xac nhan xong - vui long kiem tra lai sau it phut neu chua thay cap nhat.</p>
    <?php else: ?>
    <p>Khong tim thay thong tin don hang <?= $this->e($order_number) ?>.</p>
    <?php endif; ?>
    <a href="/shop" class="btn btn-secondary">Tiep tuc mua sam</a>
</div>
</div>
</div>
<?php $this->endSection(); ?>
