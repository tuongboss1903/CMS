<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'Admin') ?> - CMS Admin</title>
    <?php $adminCssVersion = @\filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/admin.css') ?: \time(); ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= $this->e((string) $adminCssVersion) ?>">
</head>
<body class="auth-body">
<div class="auth-shell">
    <div class="auth-card">
<?= $this->raw($this->yield('content')) ?>
    </div>
</div>
</body>
</html>
