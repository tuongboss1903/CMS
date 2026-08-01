<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<h1><?= $this->e($title ?? '') ?></h1>
<div>
<?php if (\is_array($content ?? null)): ?>
<pre><?= $this->e(\json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
<?php else: ?>
<p><?= $this->e((string) ($content ?? '')) ?></p>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
