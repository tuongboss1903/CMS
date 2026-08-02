<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">SEO: <?= $this->e($page['title']) ?></h1>
    <a href="/admin/seo" class="btn btn-secondary">Quay lai danh sach</a>
</div>

<div class="card" style="max-width: 640px;">
<form method="POST" action="/admin/seo/pages/<?= $this->e((string) $page['id']) ?>">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field">
        <label for="title">Tieu de SEO (title)</label>
        <input type="text" id="title" name="title" value="<?= $this->e((string) ($meta['title'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="description">Meta Description</label>
        <textarea id="description" name="description" rows="3"><?= $this->e((string) ($meta['description'] ?? '')) ?></textarea>
    </div>
    <div class="field">
        <label for="canonical">Canonical URL</label>
        <input type="text" id="canonical" name="canonical" value="<?= $this->e((string) ($meta['canonical'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="og_image_id">OG Image</label>
        <select id="og_image_id" name="og_image_id">
            <option value="">-- Khong co --</option>
            <?php foreach ($images as $image): ?>
            <option value="<?= $this->e((string) $image['id']) ?>" <?= (string) $image['id'] === (string) ($meta['og_image_id'] ?? '') ? 'selected' : '' ?>><?= $this->e($image['file_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="schema_type">Schema Type</label>
        <input type="text" id="schema_type" name="schema_type" value="<?= $this->e((string) ($meta['schema_type'] ?? '')) ?>" placeholder="Article, WebPage, ...">
    </div>
    <div class="field">
        <label for="schema_data_json">Schema Data (JSON)</label>
        <textarea id="schema_data_json" name="schema_data_json" rows="5" class="mb-0"><?= $this->e($schema_data_text) ?></textarea>
        <p class="text-muted mb-0" style="font-size:12px; margin-top: var(--space-2);">Nhap JSON hop le, de trong neu khong dung. Neu JSON sai dinh dang, thay doi se khong duoc luu.</p>
    </div>
    <button type="submit" class="btn btn-primary">Luu SEO</button>
</form>
</div>
<?php $this->endSection(); ?>
