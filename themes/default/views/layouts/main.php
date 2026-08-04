<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? '') ?></title>
    <meta name="robots" content="<?= ((int) ($seo['is_index'] ?? 1) === 1) ? 'index' : 'noindex' ?>,<?= ((int) ($seo['is_follow'] ?? 1) === 1) ? 'follow' : 'nofollow' ?>">
<?php if (!empty($favicon_url)): ?>
    <link rel="icon" href="<?= $this->e($favicon_url) ?>">
<?php endif; ?>
<?php if (isset($seo) && $seo !== null): ?>
<?php if (!empty($seo['description'])): ?>
    <meta name="description" content="<?= $this->e($seo['description']) ?>">
<?php endif; ?>
<?php if (!empty($seo['canonical'])): ?>
    <link rel="canonical" href="<?= $this->e($seo['canonical']) ?>">
<?php endif; ?>
    <meta property="og:title" content="<?= $this->e($seo['title'] ?? ($title ?? '')) ?>">
<?php if (!empty($seo['description'])): ?>
    <meta property="og:description" content="<?= $this->e($seo['description']) ?>">
<?php endif; ?>
<?php if (!empty($seo['schema_data'])): ?>
    <script type="application/ld+json"><?= $this->raw(\json_encode(
        $seo['schema_data'],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    )) ?></script>
<?php endif; ?>
<?php endif; ?>
<?php if (!empty($og_image_url)): ?>
    <meta property="og:image" content="<?= $this->e($og_image_url) ?>">
<?php endif; ?>
    <link rel="stylesheet" href="/assets/css/public.css">
</head>
<body class="site-public">
<header class="site-header">
    <div class="container">
        <a href="/" class="site-logo"><?php if (!empty($site_settings['site_name'])): ?><?= $this->e($site_settings['site_name']) ?><?php else: ?>CMS<span class="dot">.</span>Demo<?php endif; ?></a>
<?php if (!empty($menu)): ?>
        <button type="button" class="nav-toggle" data-nav-toggle aria-label="Toggle menu">&#9776;</button>
        <nav class="site-nav">
            <ul>
            <?php foreach ($menu as $item): ?>
                <li<?= $item['active'] ? ' class="active"' : '' ?>>
                    <a href="<?= $this->e($item['url']) ?>"<?= $item['target'] !== '_self' ? ' target="' . $this->e($item['target']) . '"' : '' ?>><?= $this->e($item['label']) ?></a>
<?php if (!empty($item['children'])): ?>
                    <ul>
<?php foreach ($item['children'] as $child): ?>
                        <li<?= $child['active'] ? ' class="active"' : '' ?>>
                            <a href="<?= $this->e($child['url']) ?>"<?= $child['target'] !== '_self' ? ' target="' . $this->e($child['target']) . '"' : '' ?>><?= $this->e($child['label']) ?></a>
                        </li>
<?php endforeach; ?>
                    </ul>
<?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        </nav>
<?php endif; ?>
    </div>
</header>
<?= $this->raw($this->yield('content')) ?>
<footer class="site-footer">
    <div class="container">&copy; <?= date('Y') ?> CMS Demo. Xay dung tren CMS da website tu code.</div>
</footer>
<script src="/assets/js/app.js"></script>
</body>
</html>
