<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'Admin') ?> - CMS Admin</title>
    <?php
    /**
     * Cache-busting bang filemtime (khong phai query string co dinh) - admin.css la static file
     * KHONG co header cache-control tuy chinh (PHP built-in server dev/production webserver deu
     * cache heuristic theo mac dinh trinh duyet) nen truoc day sua tailwind.css + build lai
     * KHONG len duoc UI ngay, phai xoa cache tay - bug UX that da gap khi audit Dashboard/Users/
     * Pages. filemtime() doi tu dong moi lan file admin.css duoc build lai (npm run build:admin),
     * khong can nho bump version tay.
     */
    $adminCssVersion = @\filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?: \time();
    ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= $this->e((string) $adminCssVersion) ?>">
<?= $this->raw($this->yield('head_extra')) ?>
</head>
<body class="admin-shell">
<a href="#main-content" class="skip-link">Bỏ qua, đến nội dung chính</a>
<aside class="admin-sidebar">
    <div class="brand">CMS<span class="dot">.</span>Admin</div>
    <?php $this->include('admin.partials.sidebar'); ?>
</aside>
<div class="admin-main">
    <?php $this->include('admin.partials.topbar', ['title' => $title ?? 'Admin']); ?>
    <main class="admin-content" id="main-content" tabindex="-1">
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
<?= $this->raw($this->yield('scripts_extra')) ?>
</body>
</html>
