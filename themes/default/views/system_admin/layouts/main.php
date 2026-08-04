<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'System Admin') ?> - CMS System Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script>try { var t = localStorage.getItem('cms-theme'); if (t === 'light' || t === 'dark') { document.documentElement.setAttribute('data-theme', t); } } catch (e) {}</script>
</head>
<body class="admin-shell">
<aside class="admin-sidebar">
    <div class="brand">CMS<span class="dot">.</span>System</div>
    <?php $this->include('system_admin.partials.sidebar'); ?>
</aside>
<div class="admin-main">
    <?php $this->include('admin.partials.topbar', ['title' => $title ?? 'System Admin', 'current_user_name' => $current_user_name ?? null]); ?>
    <main class="admin-content">
<?php $this->include('admin.partials.flash_messages', [
    'flash_success' => $flash_success ?? null,
    'flash_warning' => $flash_warning ?? null,
    'flash_error' => $flash_error ?? null,
]); ?>
<?php $this->include('admin.partials.breadcrumb', ['breadcrumb_items' => $breadcrumb_items ?? []]); ?>
<?= $this->raw($this->yield('content')) ?>
    </main>
</div>
<?php $this->include('admin.partials.confirm_modal'); ?>
<script src="/assets/js/app.js"></script>
</body>
</html>
