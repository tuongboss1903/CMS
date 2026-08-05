<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">SEO: <?= $this->e($page['title']) ?></h1>
    <a href="/admin/seo" class="btn btn-secondary">Quay lại danh sách</a>
</div>

<div class="card" style="max-width: 640px;">
<form method="POST" action="/admin/seo/pages/<?= $this->e((string) $page['id']) ?>">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
    <div class="field">
        <label for="title">Tiêu đề SEO (title)</label>
        <input type="text" id="title" name="title" value="<?= $this->e((string) ($meta['title'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="description">Mô tả Meta (Meta Description)</label>
        <textarea id="description" name="description" rows="3"><?= $this->e((string) ($meta['description'] ?? '')) ?></textarea>
    </div>
    <div class="field">
        <label for="canonical">URL chuẩn (Canonical URL)</label>
        <input type="text" id="canonical" name="canonical" value="<?= $this->e((string) ($meta['canonical'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="og_image_id">Ảnh chia sẻ (OG Image)</label>
        <select id="og_image_id" name="og_image_id">
            <option value="">-- Không có --</option>
            <?php foreach ($images as $image): ?>
            <option value="<?= $this->e((string) $image['id']) ?>" <?= (string) $image['id'] === (string) ($meta['og_image_id'] ?? '') ? 'selected' : '' ?>><?= $this->e($image['file_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="og_title">Tiêu đề chia sẻ (OG Title)</label>
        <input type="text" id="og_title" name="og_title" value="<?= $this->e((string) ($meta['og_title'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="og_description">Mô tả chia sẻ (OG Description)</label>
        <textarea id="og_description" name="og_description" rows="2"><?= $this->e((string) ($meta['og_description'] ?? '')) ?></textarea>
    </div>
    <div class="field flex gap-3" style="align-items:center;">
        <label class="mb-0" style="display:flex; align-items:center; gap:6px;">
            <input type="checkbox" name="is_index" value="1" <?= ($meta === null || (int) ($meta['is_index'] ?? 1) === 1) ? 'checked' : '' ?>>
            Cho phép index (công cụ tìm kiếm)
        </label>
        <label class="mb-0" style="display:flex; align-items:center; gap:6px;">
            <input type="checkbox" name="is_follow" value="1" <?= ($meta === null || (int) ($meta['is_follow'] ?? 1) === 1) ? 'checked' : '' ?>>
            Cho phép theo dõi liên kết (follow)
        </label>
    </div>
    <div class="field">
        <label for="schema_type">Loại dữ liệu có cấu trúc (Schema Type)</label>
        <input type="text" id="schema_type" name="schema_type" value="<?= $this->e((string) ($meta['schema_type'] ?? '')) ?>" placeholder="Article, WebPage, ...">
    </div>
    <div class="field">
        <label for="schema_data_json">Dữ liệu Schema (JSON)</label>
        <textarea id="schema_data_json" name="schema_data_json" rows="5" class="mb-0"><?= $this->e($schema_data_text) ?></textarea>
        <p class="text-muted mb-0" style="font-size:12px; margin-top: var(--space-2);">Nhập JSON hợp lệ, để trống nếu không dùng. Nếu JSON sai định dạng, thay đổi sẽ không được lưu.</p>
    </div>
    <button type="submit" class="btn btn-primary">Lưu SEO</button>
</form>
</div>
<?php $this->endSection(); ?>
