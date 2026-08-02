<?php $this->extend('admin.layouts.main'); ?>

<?php $this->section('head_extra'); ?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
#editor { background: #fff; color: #111; border-radius: 0 0 var(--radius-md) var(--radius-md); min-height: 240px; }
.ql-toolbar.ql-snow { border-radius: var(--radius-md) var(--radius-md) 0 0; background: #fff; }
</style>
<?php $this->endSection(); ?>

<?php $this->section('content'); ?>
<h1>Sua Page</h1>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
<ul>
<?php foreach ($errors as $messages): ?>
<?php foreach ($messages as $message): ?>
<li><?= $this->e($message) ?></li>
<?php endforeach; ?>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<div class="card" style="max-width: 720px;">
<form method="POST" action="/admin/pages/<?= $this->e((string) $page['id']) ?>" id="page-form">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <input type="hidden" name="editor_mode" id="editor-mode-input" value="<?= $this->e($editor_mode ?? 'quill') ?>">
    <div class="field">
        <label for="title">Tieu de</label>
        <input type="text" id="title" name="title" value="<?= $this->e($old['title'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" value="<?= $this->e($old['slug'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="parent_id">Parent Page (tuy chon)</label>
        <select id="parent_id" name="parent_id">
            <option value="">-- Khong co --</option>
            <?php foreach ($parents as $parent): ?>
            <option value="<?= $this->e((string) $parent['id']) ?>"<?= (string) $parent['id'] === (string) ($old['parent_id'] ?? '') ? ' selected' : '' ?>><?= $this->e($parent['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="template">Template (tuy chon)</label>
        <input type="text" id="template" name="template" value="<?= $this->e($old['template'] ?? '') ?>" placeholder="default">
    </div>
    <div class="field">
        <label>Noi dung theo ngon ngu</label>
        <div class="editor-mode-toggle">
            <button type="button" class="btn btn-secondary btn-sm" data-locale-tab="vi">Tieng Viet (goc)</button>
            <button type="button" class="btn btn-secondary btn-sm" data-locale-tab="en">English</button>
        </div>

        <div id="locale-pane-vi">
        <div id="quill-pane"<?= ($editor_mode ?? 'quill') === 'block' ? ' style="display:none;"' : '' ?>>
        <div id="editor"><?= $this->raw($old['content_html'] ?? '') ?></div>
        <input type="hidden" name="content[html]" id="content-html-input">
        </div>

        <div id="block-builder-pane"<?= ($editor_mode ?? 'quill') === 'block' ? '' : ' style="display:none;"' ?>>
        <?php $this->include('admin.pages.pages.blocks._builder', ['images' => $images ?? [], 'old' => $old ?? []]); ?>
        </div>
        </div>

        <div id="locale-pane-en" style="display:none;">
        <div class="field">
            <label for="translation-en-title">Tieu de (EN)</label>
            <input type="text" id="translation-en-title" name="translations[en][title]" value="<?= $this->e($translations['en']['title'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="translation-en-slug">Slug (EN)</label>
            <input type="text" id="translation-en-slug" name="translations[en][slug]" value="<?= $this->e($translations['en']['slug'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="translation-en-content">Noi dung (EN, HTML don gian)</label>
            <textarea id="translation-en-content" name="translations[en][content]" rows="8"><?= $this->e($translations['en']['content'] ?? '') ?></textarea>
        </div>
        </div>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">Cap nhat</button>
        <a href="/admin/pages" class="btn btn-secondary">Huy</a>
    </div>
</form>
</div>
<?php $this->endSection(); ?>

<?php $this->section('scripts_extra'); ?>
<script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
<script>
(function () {
    var quillEditor = new Quill('#editor', { theme: 'snow' });
    document.getElementById('page-form').addEventListener('submit', function () {
        document.getElementById('content-html-input').value = quillEditor.root.innerHTML;
    });
})();
</script>
<script src="/assets/js/page-builder.js"></script>
<script>
(function () {
    var localeButtons = document.querySelectorAll('[data-locale-tab]');
    var panes = { vi: document.getElementById('locale-pane-vi'), en: document.getElementById('locale-pane-en') };
    localeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var locale = btn.getAttribute('data-locale-tab');
            Object.keys(panes).forEach(function (key) {
                panes[key].style.display = key === locale ? '' : 'none';
            });
        });
    });
})();
</script>
<?php $this->endSection(); ?>
