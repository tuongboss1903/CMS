<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container" style="max-width: 480px;">
<div class="card" style="text-align: center;">
    <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--color-accent-soft); color: var(--color-accent-text); display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto var(--space-4);">&#10003;</div>
    <h1>Dat hang thanh cong</h1>
    <p>Cam on ban da dat hang. Ma don hang cua ban la <strong><?= $this->e((string) $order_number) ?></strong>.</p>
    <a href="/shop" class="btn btn-primary">Tiep tuc mua sam</a>
</div>
</div>
</div>
<?php $this->endSection(); ?>
