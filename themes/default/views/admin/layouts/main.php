<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'Admin') ?> - CMS Admin</title>
    <link rel="stylesheet" href="/assets/css/variables.css">
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
<?= $this->raw($this->yield('head_extra')) ?>
</head>
<body class="admin-shell">
<aside class="admin-sidebar">
    <div class="brand">CMS<span class="dot">.</span>Admin</div>
    <?php $this->include('admin.partials.sidebar'); ?>
</aside>
<div class="admin-main">
    <?php $this->include('admin.partials.topbar', ['title' => $title ?? 'Admin']); ?>
    <main class="admin-content">
<?= $this->raw($this->yield('content')) ?>
    </main>
</div>
<script src="/assets/js/app.js"></script>
<?= $this->raw($this->yield('scripts_extra')) ?>
</body>
</html>
