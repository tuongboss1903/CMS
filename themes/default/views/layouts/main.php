<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= $this->e($title ?? '') ?></title>
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
</head>
<body>
<header>
<?php if (!empty($menu)): ?>
<nav>
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
</header>
<?= $this->raw($this->yield('content')) ?>
<footer>
</footer>
</body>
</html>
