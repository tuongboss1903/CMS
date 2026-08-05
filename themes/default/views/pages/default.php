<?php $this->extend('layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="page-content">
<div class="container">
<?php if (!empty($breadcrumb) && \count($breadcrumb) > 1): ?>
<nav class="breadcrumb" aria-label="breadcrumb">
    <a href="/">Trang chủ</a>
<?php foreach ($breadcrumb as $index => $crumb): ?>
    <span class="breadcrumb-sep">/</span>
<?php if ($index === \count($breadcrumb) - 1): ?>
    <span class="breadcrumb-current"><?= $this->e($crumb['title']) ?></span>
<?php else: ?>
    <a href="/<?= $this->e($crumb['slug']) ?>"><?= $this->e($crumb['title']) ?></a>
<?php endif; ?>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if (\is_array($content ?? null) && isset($content['blocks']) && \is_array($content['blocks'])): ?>
<div class="page-body">
<?php
/**
 * Closure cuc bo (khong phai function toan cuc) - View::renderTemplate() dung include() (khong
 * include_once), khai bao function/class o top-level file view se Fatal "Cannot redeclare" khi
 * view nay render lan 2 trong cung 1 tien trinh PHP (dung tien le da ap dung o Menu Builder,
 * Phase 5). Phase 11 - Visual Page Builder: render 6 loai block MVP.
 */
$renderBlock = function (array $block) {
    $type = $block['type'] ?? '';

    if ($type === 'heading') {
        $level = \in_array((int) ($block['level'] ?? 2), [2, 3, 4], true) ? (int) $block['level'] : 2;
        echo "<h{$level}>" . $this->e((string) ($block['text'] ?? '')) . "</h{$level}>";

        return;
    }

    if ($type === 'paragraph') {
        echo '<p>' . \nl2br($this->e((string) ($block['text'] ?? ''))) . '</p>';

        return;
    }

    if ($type === 'image') {
        if (!empty($block['url'])) {
            echo '<img src="' . $this->e((string) $block['url']) . '" alt="' . $this->e((string) ($block['alt'] ?? '')) . '" style="max-width:100%; border-radius: var(--radius-md);">';
        }

        return;
    }

    if ($type === 'hero') {
        ?>
        <div class="hero">
            <h1><?= $this->e((string) ($block['headline'] ?? '')) ?></h1>
            <?php if (!empty($block['subheadline'])): ?>
            <p class="lead"><?= $this->e((string) $block['subheadline']) ?></p>
            <?php endif; ?>
            <?php if (!empty($block['cta_label'])): ?>
            <div class="hero-cta">
                <a href="<?= $this->e((string) ($block['cta_url'] ?? '#')) ?>" class="btn btn-primary"><?= $this->e((string) $block['cta_label']) ?></a>
            </div>
            <?php endif; ?>
        </div>
        <?php

        return;
    }

    if ($type === 'feature_grid') {
        ?>
        <div class="feature-grid">
            <?php foreach (($block['items'] ?? []) as $item): ?>
            <div class="feature-card">
                <div class="feature-icon"><?= $this->e((string) ($item['icon'] ?? '')) ?></div>
                <h3><?= $this->e((string) ($item['title'] ?? '')) ?></h3>
                <p><?= $this->e((string) ($item['description'] ?? '')) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php

        return;
    }

    if ($type === 'cta') {
        ?>
        <div class="cta-footer">
            <h2><?= $this->e((string) ($block['headline'] ?? '')) ?></h2>
            <?php if (!empty($block['button_label'])): ?>
            <a href="<?= $this->e((string) ($block['button_url'] ?? '#')) ?>" class="btn btn-primary"><?= $this->e((string) $block['button_label']) ?></a>
            <?php endif; ?>
        </div>
        <?php
    }
};

    foreach ($content['blocks'] as $block) {
        if (\is_array($block)) {
            $renderBlock($block);
        }
    }
    ?>
</div>
<?php elseif (\is_array($content ?? null) && isset($content['html'])): ?>
<div class="page-body">
<?= $this->raw((string) $content['html']) ?>
</div>
<?php else: ?>
<h1><?= $this->e($title ?? '') ?></h1>
<div class="page-body">
<?php if (\is_array($content ?? null) && isset($content['text'])): ?>
<p><?= $this->e((string) $content['text']) ?></p>
<?php elseif (\is_array($content ?? null)): ?>
<pre><?= $this->e(\json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
<?php else: ?>
<p><?= $this->e((string) ($content ?? '')) ?></p>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (isset($comment_csrf_token) || isset($comments)): ?>
<div class="comments-section" style="margin-top: var(--space-6);">
    <h2>Bình luận (<?= $this->e((string) \count($comments ?? [])) ?>)</h2>

    <?php if (!empty($comment_success)): ?>
    <div class="alert alert-success"><?= $this->e((string) $comment_success) ?></div>
    <?php endif; ?>

    <?php if (!empty($comment_errors)): ?>
    <div class="alert alert-danger">
    <ul>
    <?php foreach ($comment_errors as $messages): ?>
    <?php foreach ((array) $messages as $message): ?>
    <li><?= $this->e((string) $message) ?></li>
    <?php endforeach; ?>
    <?php endforeach; ?>
    </ul>
    </div>
    <?php endif; ?>

    <?php foreach (($comments ?? []) as $comment): ?>
    <div class="card" style="margin-top: var(--space-3);">
        <strong><?= $this->e((string) $comment['guest_name']) ?></strong>
        <span class="text-muted"> - <?= $this->e((string) $comment['created_at']) ?></span>
        <p><?= \nl2br($this->e((string) $comment['body'])) ?></p>
    </div>
    <?php endforeach; ?>
    <?php if (empty($comments)): ?>
    <p class="empty-state">Chưa có bình luận nào.</p>
    <?php endif; ?>

    <?php if (isset($comment_csrf_token)): ?>
    <form method="POST" action="/<?= $this->e((string) ($page_slug ?? '')) ?>/comments" class="card" style="margin-top: var(--space-4);">
        <input type="hidden" name="_token" value="<?= $this->e((string) $comment_csrf_token) ?>">
        <div class="field">
            <label for="guest_name">Tên của bạn</label>
            <input type="text" id="guest_name" name="guest_name" required>
        </div>
        <div class="field">
            <label for="guest_email">Email (không hiển thị công khai)</label>
            <input type="email" id="guest_email" name="guest_email" required>
        </div>
        <div class="field">
            <label for="body">Bình luận</label>
            <textarea id="body" name="body" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Gửi bình luận</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>
<?php $this->endSection(); ?>
