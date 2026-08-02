<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<?php if (!empty($breadcrumb) && \count($breadcrumb) > 1): ?>
<nav class="breadcrumb" aria-label="breadcrumb">
    <a href="/">Trang chu</a>
<?php foreach ($breadcrumb as $index => $crumb): ?>
    <span class="breadcrumb-sep">/</span>
<?php if ($index === \count($breadcrumb) - 1): ?>
    <span class="breadcrumb-current"><?= $this->e($crumb['title']) ?></span>
<?php else: ?>
    <a href="/<?= $this->e($crumb['slug']) ?>"><?= $this->e($crumb['title']) ?></a>
<?php endif; ?>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<h1><?= $this->e($title ?? '') ?></h1>
<div class="page-body">
<?php if (\is_array($content ?? null) && isset($content['html'])): ?>
<?= $this->raw((string) $content['html']) ?>
<?php elseif (\is_array($content ?? null) && isset($content['text'])): ?>
<p><?= $this->e((string) $content['text']) ?></p>
<?php elseif (\is_array($content ?? null)): ?>
<pre><?= $this->e(\json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
<?php else: ?>
<p><?= $this->e((string) ($content ?? '')) ?></p>
<?php endif; ?>
</div>
</div>
</div>
<?php $this->endSection(); ?>
