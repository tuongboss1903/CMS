<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<h1>Kết quả tìm kiếm</h1>
<?php if ($query !== ''): ?>
<p>Kết quả cho: "<?= $this->e($query) ?>"</p>
<?php endif; ?>

<?php if ($query === ''): ?>
<p>Nhập từ khoá để tìm kiếm.</p>
<?php elseif (empty($results)): ?>
<p>Không tìm thấy kết quả nào phù hợp.</p>
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
