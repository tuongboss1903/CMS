<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quản lý thanh toán</h1>
<p class="text-muted mb-5">Bật/tắt cổng thanh toán khách hàng có thể chọn khi đặt hàng tại Cửa hàng.</p>

<div class="card">
<?php foreach ($drivers as $driver): ?>
<div class="flex items-center justify-between" style="padding:var(--space-4) 0;border-bottom:1px solid var(--color-border);">
    <span><?= $this->e((string) $driver['label']) ?></span>
    <form method="POST" action="/admin/payment-settings/<?= $this->e((string) $driver['key']) ?>/toggle" data-confirm="<?= $driver['enabled'] ? 'Tắt cổng thanh toán này?' : 'Bật cổng thanh toán này?' ?>">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <button type="submit" class="switch<?= $driver['enabled'] ? ' is-on' : '' ?>" role="switch" aria-checked="<?= $driver['enabled'] ? 'true' : 'false' ?>" aria-label="<?= $this->e('Bật/tắt ' . (string) $driver['label']) ?>"></button>
    </form>
</div>
<?php endforeach; ?>
</div>
<?php $this->endSection(); ?>
