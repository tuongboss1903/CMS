<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Search</h1>
<?php if ($query !== ''): ?>
<p>Query: <?= $this->e($query) ?></p>
<?php endif; ?>
<?php foreach ($results as $result): ?>
<p><?= $this->e($result['title']) ?></p>
<?php endforeach; ?>
<?php $this->endSection(); ?>
