<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between mb-5" style="flex-wrap: wrap; gap: var(--space-3);">
    <h1 class="mb-0">Quản lý Media</h1>
    <div class="flex gap-3" style="align-items:center;">
        <!-- Phase 18 (CMS-055): Grid/List toggle - CSS/JS thuan, dung lai nguyen $media da co,
             khong can Controller cung cap them du lieu gi. -->
        <div class="view-toggle" data-view-toggle>
            <button type="button" data-view="grid" class="is-active">Lưới</button>
            <button type="button" data-view="list">Danh sách</button>
        </div>
        <button type="button" class="btn btn-secondary" data-modal-open="folder-modal"><?php $this->include('admin.partials.icon', ['name' => 'folder']); ?> Thư mục</button>
        <button type="button" class="btn btn-primary" data-modal-open="upload-modal"><?php $this->include('admin.partials.icon', ['name' => 'upload']); ?> Tải lên</button>
    </div>
</div>

<?php $currentFolderId = $filters['folder_id'] ?? ''; ?>
<div class="table-filter-tabs">
    <a href="/admin/media" class="btn <?= $currentFolderId === '' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tất cả</a>
    <a href="/admin/media?folder_id=0" class="btn <?= $currentFolderId === '0' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Chưa phân loại</a>
    <?php foreach ($folders as $folder): ?>
    <a href="/admin/media?folder_id=<?= $this->e((string) $folder['id']) ?>" class="btn <?= $currentFolderId === (string) $folder['id'] ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'folder']); ?> <?= $this->e($folder['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/admin/media',
    'filter_fields' => [
        ['name' => 'q', 'label' => 'Tìm theo tên file', 'type' => 'text', 'value' => $filters['q'] ?? ''],
        ['name' => 'type', 'label' => 'Loại file', 'type' => 'select', 'value' => $filters['type'] ?? '', 'options' => [
            ['value' => 'image/', 'label' => 'Hình ảnh'],
            ['value' => 'application/pdf', 'label' => 'PDF'],
        ]],
    ],
]); ?>

<div class="media-grid" data-view-panel="grid">
<?php foreach ($media as $item): ?>
<div class="media-card">
    <?php if (\str_starts_with((string) $item['mime_type'], 'image/')): ?>
    <img src="/admin/media/<?= $this->e((string) $item['id']) ?>/thumbnail" alt="<?= $this->e((string) (($item['alt_text'] ?? '') !== '' ? $item['alt_text'] : $item['file_name'])) ?>" loading="lazy">
    <?php else: ?>
    <div class="media-card-icon">FILE</div>
    <?php endif; ?>
    <div class="media-card-body">
        <div class="media-card-name"><?= $this->e($item['file_name']) ?></div>
        <div class="text-muted" style="font-size:12px;"><?= $this->e((string) $item['mime_type']) ?> &middot; <?= $this->e((string) \round(((int) $item['size']) / 1024, 1)) ?> KB</div>

        <form method="POST" action="/admin/media/<?= $this->e((string) $item['id']) ?>" class="media-edit-form">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <div class="field">
                <label for="media-<?= $this->e((string) $item['id']) ?>-alt">Văn bản thay thế (Alt)</label>
                <input type="text" id="media-<?= $this->e((string) $item['id']) ?>-alt" name="alt_text" value="<?= $this->e((string) ($item['alt_text'] ?? '')) ?>">
            </div>
            <div class="field">
                <label for="media-<?= $this->e((string) $item['id']) ?>-title">Tiêu đề</label>
                <input type="text" id="media-<?= $this->e((string) $item['id']) ?>-title" name="title" value="<?= $this->e((string) ($item['title'] ?? '')) ?>">
            </div>
            <div class="field">
                <label for="media-<?= $this->e((string) $item['id']) ?>-caption">Chú thích</label>
                <input type="text" id="media-<?= $this->e((string) $item['id']) ?>-caption" name="caption" value="<?= $this->e((string) ($item['caption'] ?? '')) ?>">
            </div>
            <div class="field">
                <label for="media-<?= $this->e((string) $item['id']) ?>-folder">Thư mục</label>
                <select id="media-<?= $this->e((string) $item['id']) ?>-folder" name="folder_id">
                    <option value="">-- Chưa phân loại --</option>
                    <?php foreach ($folders as $folder): ?>
                    <option value="<?= $this->e((string) $folder['id']) ?>" <?= (int) ($item['folder_id'] ?? 0) === (int) $folder['id'] ? 'selected' : '' ?>><?= $this->e($folder['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Lưu</button>
        </form>

        <form method="POST" action="/admin/media/<?= $this->e((string) $item['id']) ?>/delete" data-confirm="Xác nhận xoá file này?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($media)): ?>
<div class="empty-state">Chưa có file nào.</div>
<?php endif; ?>
</div>

<div class="table-wrap is-hidden" data-view-panel="list" data-bulk-select="media-bulk-form">
    <div class="bulk-actions-bar" data-bulk-bar>
        <span data-bulk-count>0 mục đã chọn</span>
        <button type="submit" form="media-bulk-form" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá đã chọn</button>
        <button type="button" class="btn btn-ghost btn-sm" data-bulk-clear>Bỏ chọn</button>
    </div>
    <table class="data-table media-list-table">
    <thead>
    <tr><th scope="col" class="table-select-col"><input type="checkbox" data-select-all aria-label="Chọn tất cả file"></th><th scope="col"></th><th scope="col">Tên file</th><th scope="col">Loại</th><th scope="col">Dung lượng</th><th scope="col"></th></tr>
    </thead>
    <tbody>
    <?php foreach ($media as $item): ?>
    <tr>
        <td><input type="checkbox" name="ids[]" value="<?= $this->e((string) $item['id']) ?>" form="media-bulk-form" data-select-row aria-label="<?= $this->e('Chọn file ' . (string) $item['file_name']) ?>"></td>
        <td>
        <?php if (\str_starts_with((string) $item['mime_type'], 'image/')): ?>
        <img class="media-thumb" src="/admin/media/<?= $this->e((string) $item['id']) ?>/thumbnail" alt="" loading="lazy">
        <?php endif; ?>
        </td>
        <td><?= $this->e($item['file_name']) ?></td>
        <td class="text-muted"><?= $this->e((string) $item['mime_type']) ?></td>
        <td class="text-muted"><?= $this->e((string) \round(((int) $item['size']) / 1024, 1)) ?> KB</td>
        <td>
            <form method="POST" action="/admin/media/<?= $this->e((string) $item['id']) ?>/delete" data-confirm="Xác nhận xoá file này?">
                <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
                <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($media)): ?>
    <tr><td colspan="6" class="empty-state">Chưa có file nào.</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
</div>
<!-- Form rieng, khong long trong .table-wrap - checkbox/nut ben tren tro toi qua thuoc tinh
     form="media-bulk-form" (HTML5 hop le, tranh loi <form> long <form> voi form xoa tung dong). -->
<form id="media-bulk-form" method="POST" action="/admin/media/bulk-delete" data-confirm="Xoá các file đã chọn? Không thể hoàn tác.">
    <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
</form>

<div class="modal-overlay" id="folder-modal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="folder-modal-title" tabindex="-1">
        <h2 id="folder-modal-title">Thư mục Media</h2>
        <?php if (empty($folders)): ?>
        <p class="text-muted" style="font-size:13px;">Chưa có thư mục nào.</p>
        <?php else: ?>
        <ul style="list-style:none; margin:0 0 var(--space-4); padding:0;">
        <?php foreach ($folders as $folder): ?>
        <li class="flex items-center justify-between" style="padding: var(--space-2) 0; border-bottom: 1px solid var(--color-border-subtle);">
            <span class="flex items-center gap-2"><?php $this->include('admin.partials.icon', ['name' => 'folder']); ?> <?= $this->e($folder['name']) ?></span>
            <form method="POST" action="/admin/media/folders/<?= $this->e((string) $folder['id']) ?>/delete" data-confirm="Xoá thư mục này? File bên trong sẽ chuyển về Chưa phân loại.">
                <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
                <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
            </form>
        </li>
        <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <form method="POST" action="/admin/media/folders">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <div class="field">
                <label for="folder-name">Tên thư mục mới</label>
                <input type="text" id="folder-name" name="name" required>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Tạo thư mục</button>
                <button type="button" class="btn btn-secondary" data-modal-close="folder-modal">Đóng</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="upload-modal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="upload-modal-title" tabindex="-1">
        <h2 id="upload-modal-title">Tải file lên</h2>
        <form method="POST" action="/admin/media" enctype="multipart/form-data" id="upload-form">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <div class="field media-dropzone" id="media-dropzone">
                <label for="media-file-input" class="text-muted mb-0" style="display:block;">Kéo thả file vào đây hoặc bấm để chọn file (JPEG/PNG/GIF/PDF, tối đa 5MB)</label>
                <input type="file" name="file" id="media-file-input">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Tải lên</button>
                <button type="button" class="btn btn-secondary" data-modal-close="upload-modal">Huỷ</button>
            </div>
        </form>
    </div>
</div>
<?php $this->endSection(); ?>
