<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
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
