<!DOCTYPE html>
<html>
<head>
<title><?= $this->e($title ?? '') ?></title>
<meta name="robots" content="<?= ((int) ($seo['is_index'] ?? 1) === 1) ? 'index' : 'noindex' ?>,<?= ((int) ($seo['is_follow'] ?? 1) === 1) ? 'follow' : 'nofollow' ?>">
<?php if (!empty($favicon_url)): ?>
<link rel="icon" href="<?= $this->e($favicon_url) ?>">
<?php endif; ?>
<?php if (!empty($og_image_url)): ?>
<meta property="og:image" content="<?= $this->e($og_image_url) ?>">
<?php endif; ?>
</head>
<body><?= $this->raw($this->yield('content')) ?></body>
</html>
