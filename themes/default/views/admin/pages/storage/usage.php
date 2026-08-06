<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Dung lượng lưu trữ</h1>

<?php
// Closure cuc bo (khong phai function toan cuc) - tranh fatal "Cannot redeclare" khi View render()
// nhieu lan cung 1 tien trinh (vd PHPUnit), dung bai hoc da rut ra o CMS-076 (Dashboard).
$formatBytes = static function (int $bytes): string {
    if ($bytes >= 1024 * 1024 * 1024) {
        return \number_format($bytes / (1024 * 1024 * 1024), 2, ',', '.') . ' GB';
    }

    return \number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
};

$limitBytes = $limit_mb !== null ? $limit_mb * 1024 * 1024 : null;
$barColor = $percent_used === null || $percent_used < 80 ? 'var(--color-accent-secondary)' : ($percent_used < 95 ? 'var(--color-warning)' : 'var(--color-danger)');
?>

<div class="card mb-5">
    <div class="flex items-center justify-between mb-2">
        <span class="text-muted">Đã sử dụng</span>
        <strong>
            <?= $this->e($formatBytes($used_bytes)) ?>
            <?php if ($limitBytes !== null): ?>
            / <?= $this->e($formatBytes($limitBytes)) ?>
            <?php else: ?>
            (không giới hạn)
            <?php endif; ?>
        </strong>
    </div>
    <?php if ($percent_used !== null): ?>
    <div style="height:10px;border-radius:var(--radius-full);background:var(--color-surface-2);overflow:hidden;">
        <div style="height:100%;width:<?= $this->e((string) \round($percent_used, 1)) ?>%;background:<?= $barColor ?>;border-radius:var(--radius-full);"></div>
    </div>
    <p class="text-muted mt-2" style="font-size:var(--text-xs);"><?= $this->e((string) \round($percent_used, 1)) ?>% dung lượng gói dịch vụ đã dùng.</p>
    <?php endif; ?>
</div>

<div class="card mb-5">
    <h2>Phân loại theo định dạng</h2>
    <div class="table-wrap table-wrap--flat">
    <table class="data-table">
    <thead><tr><th scope="col">Loại</th><th scope="col">Số lượng file</th><th scope="col">Dung lượng</th></tr></thead>
    <tbody>
    <?php foreach ($breakdown as $item): ?>
    <tr>
        <td><?= $this->e($item['label']) ?></td>
        <td><?= $this->e((string) $item['count']) ?></td>
        <td style="font-family:var(--font-mono);"><?= $this->e($formatBytes($item['size_bytes'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
</div>

<div class="card">
    <h2>File chiếm dung lượng lớn nhất</h2>
    <div class="table-wrap table-wrap--flat">
    <table class="data-table">
    <thead><tr><th scope="col">Tên file</th><th scope="col">Dung lượng</th><th scope="col">Thời gian tải lên</th></tr></thead>
    <tbody>
    <?php foreach ($largest_files as $file): ?>
    <tr>
        <td><?= $this->e((string) $file['file_name']) ?></td>
        <td style="font-family:var(--font-mono);"><?= $this->e($formatBytes((int) $file['size'])) ?></td>
        <td class="text-muted"><?= $this->e((string) $file['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($largest_files)): ?>
    <tr><td colspan="3" class="empty-state">Chưa có file nào được tải lên.</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
    </div>
</div>
<?php $this->endSection(); ?>
