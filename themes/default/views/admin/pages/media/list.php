<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5); flex-wrap: wrap; gap: var(--space-3);">
    <h1 class="mb-0">Quan ly Media</h1>
    <div class="flex gap-3" style="align-items:center;">
        <!-- Phase 18 (CMS-055): Grid/List toggle - CSS/JS thuan, dung lai nguyen $media da co,
             khong can Controller cung cap them du lieu gi. -->
        <div class="view-toggle" data-view-toggle>
            <button type="button" data-view="grid" class="is-active">Grid</button>
            <button type="button" data-view="list">List</button>
        </div>
        <button type="button" class="btn btn-primary" data-modal-open="upload-modal">+ Tai len</button>
    </div>
</div>

<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/admin/media',
    'filter_fields' => [
        ['name' => 'q', 'label' => 'Tim theo ten file', 'type' => 'text', 'value' => $filters['q'] ?? ''],
        ['name' => 'type', 'label' => 'Loai file', 'type' => 'select', 'value' => $filters['type'] ?? '', 'options' => [
            ['value' => 'image/', 'label' => 'Hinh anh'],
            ['value' => 'application/pdf', 'label' => 'PDF'],
        ]],
    ],
]); ?>

<div class="media-grid" data-view-panel="grid">
<?php foreach ($media as $item): ?>
<div class="media-card">
    <?php if (\str_starts_with((string) $item['mime_type'], 'image/')): ?>
    <img src="/admin/media/<?= $this->e((string) $item['id']) ?>/file" alt="<?= $this->e((string) ($item['alt_text'] ?? '')) ?>">
    <?php else: ?>
    <div class="media-card-icon">FILE</div>
    <?php endif; ?>
    <div class="media-card-body">
        <div class="media-card-name"><?= $this->e($item['file_name']) ?></div>
        <div class="text-muted" style="font-size:12px;"><?= $this->e((string) $item['mime_type']) ?> &middot; <?= $this->e((string) \round(((int) $item['size']) / 1024, 1)) ?> KB</div>

        <form method="POST" action="/admin/media/<?= $this->e((string) $item['id']) ?>" class="media-edit-form">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <div class="field">
                <label>Alt text</label>
                <input type="text" name="alt_text" value="<?= $this->e((string) ($item['alt_text'] ?? '')) ?>">
            </div>
            <div class="field">
                <label>Tieu de</label>
                <input type="text" name="title" value="<?= $this->e((string) ($item['title'] ?? '')) ?>">
            </div>
            <div class="field">
                <label>Chu thich</label>
                <input type="text" name="caption" value="<?= $this->e((string) ($item['caption'] ?? '')) ?>">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Luu</button>
        </form>

        <form method="POST" action="/admin/media/<?= $this->e((string) $item['id']) ?>/delete" data-confirm="Xac nhan xoa file nay?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($media)): ?>
<div class="empty-state">Chua co file nao.</div>
<?php endif; ?>
</div>

<div class="table-wrap is-hidden" data-view-panel="list">
<table class="data-table media-list-table">
<thead>
<tr><th></th><th>Ten file</th><th>Loai</th><th>Dung luong</th><th></th></tr>
</thead>
<tbody>
<?php foreach ($media as $item): ?>
<tr>
    <td>
    <?php if (\str_starts_with((string) $item['mime_type'], 'image/')): ?>
    <img class="media-thumb" src="/admin/media/<?= $this->e((string) $item['id']) ?>/file" alt="">
    <?php endif; ?>
    </td>
    <td><?= $this->e($item['file_name']) ?></td>
    <td class="text-muted"><?= $this->e((string) $item['mime_type']) ?></td>
    <td class="text-muted"><?= $this->e((string) \round(((int) $item['size']) / 1024, 1)) ?> KB</td>
    <td>
        <form method="POST" action="/admin/media/<?= $this->e((string) $item['id']) ?>/delete" data-confirm="Xac nhan xoa file nay?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Xoa</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($media)): ?>
<tr><td colspan="5" class="empty-state">Chua co file nao.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<div class="modal-overlay" id="upload-modal">
    <div class="modal">
        <h2>Tai file len</h2>
        <form method="POST" action="/admin/media" enctype="multipart/form-data" id="upload-form">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <div class="field media-dropzone" id="media-dropzone">
                <input type="file" name="file" id="media-file-input">
                <p class="text-muted mb-0">Keo tha file vao day hoac bam de chon file (JPEG/PNG/GIF/PDF, toi da 5MB)</p>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Tai len</button>
                <button type="button" class="btn btn-secondary" data-modal-close="upload-modal">Huy</button>
            </div>
        </form>
    </div>
</div>
<?php $this->endSection(); ?>
