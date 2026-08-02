<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Cai dat chung</h1>

<div class="card" style="max-width: 640px;">
<form method="POST" action="/admin/settings">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field">
        <label for="site_name">Ten website</label>
        <input type="text" id="site_name" name="site_name" value="<?= $this->e((string) ($settings['site_name'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="site_tagline">Slogan</label>
        <input type="text" id="site_tagline" name="site_tagline" value="<?= $this->e((string) ($settings['site_tagline'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="default_meta_description">Meta Description mac dinh</label>
        <textarea id="default_meta_description" name="default_meta_description" rows="3"><?= $this->e((string) ($settings['default_meta_description'] ?? '')) ?></textarea>
        <p class="text-muted mb-0" style="font-size:12px; margin-top: var(--space-2);">Dung khi 1 Page chua co SEO Meta rieng.</p>
    </div>
    <div class="field">
        <label for="default_og_image_id">OG Image mac dinh</label>
        <select id="default_og_image_id" name="default_og_image_id">
            <option value="">-- Khong co --</option>
            <?php foreach ($images as $image): ?>
            <option value="<?= $this->e((string) $image['id']) ?>" <?= (string) $image['id'] === (string) ($settings['default_og_image_id'] ?? '') ? 'selected' : '' ?>><?= $this->e($image['file_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="favicon_id">Favicon</label>
        <select id="favicon_id" name="favicon_id">
            <option value="">-- Khong co --</option>
            <?php foreach ($images as $image): ?>
            <option value="<?= $this->e((string) $image['id']) ?>" <?= (string) $image['id'] === (string) ($settings['favicon_id'] ?? '') ? 'selected' : '' ?>><?= $this->e($image['file_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="robots_txt_custom">Robots.txt tuy chinh (de trong de dung mac dinh)</label>
        <textarea id="robots_txt_custom" name="robots_txt_custom" rows="5"><?= $this->e((string) ($settings['robots_txt_custom'] ?? '')) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Luu cai dat</button>
</form>
</div>
<?php $this->endSection(); ?>
