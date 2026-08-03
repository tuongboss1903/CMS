<?php
/**
 * Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055). Bien: $breadcrumb_items (list cua
 * array{label: string, url?: string|null}) - phan tu CUOI CUNG luon render nhu muc dang active
 * (khong link), bat ke co 'url' hay khong. Khong render gi neu rong (trang khong can breadcrumb).
 */
$items = $breadcrumb_items ?? [];

if (empty($items)) {
    return;
}

$lastIndex = \count($items) - 1;
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
<?php foreach ($items as $index => $item): ?>
<?php if ($index > 0): ?><span class="crumb-sep">/</span><?php endif; ?>
<?php if ($index !== $lastIndex && !empty($item['url'])): ?>
<a href="<?= $this->e((string) $item['url']) ?>"><?= $this->e((string) $item['label']) ?></a>
<?php else: ?>
<span class="crumb-current"><?= $this->e((string) $item['label']) ?></span>
<?php endif; ?>
<?php endforeach; ?>
</nav>
