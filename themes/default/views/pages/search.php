<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1>Ket qua tim kiem</h1>
<?php if ($query !== ''): ?>
<p>Ket qua cho: "<?= $this->e($query) ?>"</p>
<?php endif; ?>

<?php if ($query === ''): ?>
<p>Nhap tu khoa de tim kiem.</p>
<?php elseif (empty($results)): ?>
<p>Khong tim thay ket qua nao phu hop.</p>
<?php else: ?>
<ul>
<?php foreach ($results as $result): ?>
<li><a href="/<?= $this->e($result['slug']) ?>"><?= $this->e($result['title']) ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
</div>
<?php $this->endSection(); ?>
