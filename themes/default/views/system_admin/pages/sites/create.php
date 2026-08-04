<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Tao site moi</h1>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
<ul>
<?php foreach ($errors as $messages): ?>
<?php foreach ($messages as $message): ?>
<li><?= $this->e($message) ?></li>
<?php endforeach; ?>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<form method="POST" action="/system-admin/sites" class="card" style="max-width: 480px;">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <div class="field">
        <label for="name">Ten Site</label>
        <input type="text" id="name" name="name" value="<?= $this->e($old['name'] ?? '') ?>" autofocus>
    </div>
    <div class="field">
        <label for="domain">Domain chinh</label>
        <input type="text" id="domain" name="domain" value="<?= $this->e($old['domain'] ?? '') ?>" placeholder="vidu.com">
    </div>
    <button type="submit" class="btn btn-primary">Tao Site</button>
    <a href="/system-admin/sites" class="btn btn-secondary">Huy</a>
</form>
<?php $this->endSection(); ?>
