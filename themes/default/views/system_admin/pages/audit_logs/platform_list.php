<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Nhật ký Super Admin</h1>
<p class="text-muted">Hành động của chính Super Admin (tạo/sửa/tạm khoá site, bật/tắt plugin...) — <?= $this->e((string) $total) ?> bản ghi phù hợp bộ lọc.</p>

<?php
$eventOptions = \array_map(
    static fn (string $eventOption): array => ['value' => $eventOption, 'label' => $eventOption],
    $available_events
);
?>
<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/system-admin/platform-audit-logs',
    'filter_fields' => [
        ['name' => 'event', 'label' => 'Sự kiện', 'type' => 'select', 'value' => $filters['event'], 'options' => $eventOptions],
    ],
]); ?>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th scope="col">Thời gian</th><th scope="col">Super Admin</th><th scope="col">Sự kiện</th><th scope="col">Site</th><th scope="col">IP</th><th scope="col">Chi tiết</th></tr>
</thead>
<tbody>
<?php foreach ($logs as $log): ?>
<tr>
    <td class="text-muted"><?= $this->e((string) $log['created_at']) ?></td>
    <td><?= $this->e((string) ($log['admin_name'] ?? '-')) ?></td>
    <td><span class="badge badge-neutral"><?= $this->e((string) $log['event']) ?></span></td>
    <td><?= $this->e((string) ($log['site_name'] ?? '-')) ?></td>
    <td class="text-muted"><?= $this->e((string) ($log['ip_address'] ?? '-')) ?></td>
    <td>
    <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
        <details>
        <summary>Xem</summary>
        <?php if (!empty($log['old_values'])): ?>
        <div><strong>Trước:</strong> <code><?= $this->e((string) $log['old_values']) ?></code></div>
        <?php endif; ?>
        <?php if (!empty($log['new_values'])): ?>
        <div><strong>Sau:</strong> <code><?= $this->e((string) $log['new_values']) ?></code></div>
        <?php endif; ?>
        </details>
    <?php else: ?>
        -
    <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($logs)): ?>
<tr><td colspan="6" class="empty-state">Không có nhật ký nào phù hợp.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<?php $this->include('admin.partials.pagination', [
    'page' => $page,
    'total_pages' => $total_pages,
    'base_url' => '/system-admin/platform-audit-logs?' . \http_build_query(['event' => $filters['event']]) . '&',
]); ?>
<?php $this->endSection(); ?>
