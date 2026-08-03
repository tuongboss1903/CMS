<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1>Dat hang thanh cong</h1>
<p>Cam on ban da dat hang. Ma don hang cua ban la <strong><?= $this->e((string) $order_number) ?></strong>.</p>
<a href="/shop" class="btn btn-secondary">Tiep tuc mua sam</a>
</div>
</div>
<?php $this->endSection(); ?>
