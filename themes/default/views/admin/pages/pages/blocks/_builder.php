<?php
/**
 * Partial dung chung giua create.php/edit.php (Phase 11 - Visual Page Builder). Can duoc include
 * qua $this->include('admin.pages.pages.blocks._builder', [...]) voi du lieu: $images (list media
 * anh cua tenant), $old['blocks'] (mang block hien co, rong neu Page moi/dang dung Quill).
 * Toggle + danh sach Block duoc JS (public/assets/js/page-builder.js) dieu khien hoan toan - view
 * nay chi dung khung HTML + du lieu khoi tao ban dau qua data-attribute.
 */
$initialBlocks = $old['blocks'] ?? [];
$imagesForJs = \array_map(
    static fn (array $image): array => ['id' => $image['id'], 'file_name' => $image['file_name']],
    $images ?? []
);
?>
<div class="editor-mode-toggle">
    <button type="button" class="btn btn-secondary btn-sm" data-editor-mode-toggle="quill">Soạn thảo văn bản</button>
    <button type="button" class="btn btn-secondary btn-sm" data-editor-mode-toggle="block">Trình dựng Block</button>
</div>

<div id="block-builder"
     data-images='<?= $this->e(\json_encode($imagesForJs, JSON_UNESCAPED_UNICODE)) ?>'
     data-initial-blocks='<?= $this->e(\json_encode($initialBlocks, JSON_UNESCAPED_UNICODE)) ?>'>
    <div id="block-list"></div>
    <div class="block-editor-add-bar">
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="heading"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tiêu đề</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="paragraph"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Đoạn văn</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="image"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Hình ảnh</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="hero"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Hero</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="feature_grid"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Lưới tính năng</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="cta"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> CTA</button>
    </div>
</div>
<input type="hidden" name="content_blocks_json" id="content-blocks-input">
