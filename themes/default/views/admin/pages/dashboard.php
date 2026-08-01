<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Dashboard</h1>
<p>user_count: <span data-field="user_count"><?= $this->e((string) $user_count) ?></span></p>
<p>role_count: <span data-field="role_count"><?= $this->e((string) $role_count) ?></span></p>
<form method="POST" action="/admin/logout">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
    <button type="submit">Dang xuat</button>
</form>
<?php $this->endSection(); ?>
