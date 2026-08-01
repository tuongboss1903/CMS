<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<h1><?= $this->e($title ?? '') ?></h1>
<p data-template="default"><?= $this->e(\is_array($content ?? null) ? \json_encode($content) : (string) ($content ?? '')) ?></p>
<?php $this->endSection(); ?>
