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
    <button type="button" class="btn btn-secondary btn-sm" data-editor-mode-toggle="quill">Rich Text</button>
    <button type="button" class="btn btn-secondary btn-sm" data-editor-mode-toggle="block">Block Builder</button>
</div>

<div id="block-builder"
     data-images='<?= $this->e(\json_encode($imagesForJs, JSON_UNESCAPED_UNICODE)) ?>'
     data-initial-blocks='<?= $this->e(\json_encode($initialBlocks, JSON_UNESCAPED_UNICODE)) ?>'>
    <div id="block-list"></div>
    <div class="block-editor-add-bar">
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="heading">+ Heading</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="paragraph">+ Paragraph</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="image">+ Image</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="hero">+ Hero</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="feature_grid">+ Feature Grid</button>
        <button type="button" class="btn btn-secondary btn-sm" data-add-block="cta">+ CTA</button>
    </div>
</div>
<input type="hidden" name="content_blocks_json" id="content-blocks-input">
