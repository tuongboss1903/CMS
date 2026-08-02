<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<?php if (!empty($breadcrumb) && \count($breadcrumb) > 1): ?>
<nav class="breadcrumb">
<?php foreach ($breadcrumb as $crumb): ?>
<span class="breadcrumb-item"><?= $this->e($crumb['title']) ?></span>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<h1><?= $this->e($title ?? '') ?></h1>
<p data-template="default"><?= $this->e(\is_array($content ?? null) ? \json_encode($content) : (string) ($content ?? '')) ?></p>
<?php $this->endSection(); ?>
