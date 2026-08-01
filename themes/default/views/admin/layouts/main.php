<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= $this->e($title ?? 'Admin') ?></title>
</head>
<body>
<?= $this->raw($this->yield('content')) ?>
</body>
</html>
