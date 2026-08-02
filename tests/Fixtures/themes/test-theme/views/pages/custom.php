<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<h1 data-template="custom"><?= $this->e($title ?? '') ?></h1>
<?php $this->endSection(); ?>
