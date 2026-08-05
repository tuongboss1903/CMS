<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Nhật ký hoạt động (xuyên site)</h1>
<p class="text-muted">Nhật ký hoạt động của Site Admin/Người dùng trên TẤT CẢ site — <?= $this->e((string) $total) ?> bản ghi phù hợp bộ lọc.</p>

<?php
$eventOptions = \array_map(
    static fn (string $eventOption): array => ['value' => $eventOption, 'label' => $eventOption],
    $available_events
);
$siteOptions = \array_map(
    static fn (array $site): array => ['value' => (string) $site['id'], 'label' => (string) $site['name']],
    $sites
);
?>
<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/system-admin/audit-logs',
    'filter_fields' => [
        ['name' => 'site_id', 'label' => 'Site', 'type' => 'select', 'value' => $filters['site_id'], 'options' => $siteOptions],
        ['name' => 'event', 'label' => 'Sự kiện', 'type' => 'select', 'value' => $filters['event'], 'options' => $eventOptions],
        ['name' => 'date_from', 'label' => 'Từ ngày', 'type' => 'date', 'value' => $filters['date_from']],
        ['name' => 'date_to', 'label' => 'Đến ngày', 'type' => 'date', 'value' => $filters['date_to']],
    ],
]); ?>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Thời gian</th><th>Site</th><th>Sự kiện</th><th>Người dùng ID</th><th>Đối tượng</th><th>IP</th></tr>
</thead>
<tbody>
<?php foreach ($logs as $log): ?>
<tr>
    <td class="text-muted"><?= $this->e((string) $log['created_at']) ?></td>
    <td><?= $this->e((string) ($log['site_name'] ?? '-')) ?></td>
    <td><span class="badge badge-neutral"><?= $this->e((string) $log['event']) ?></span></td>
    <td><?= $this->e((string) ($log['user_id'] ?? '-')) ?></td>
    <td>
    <?php if (!empty($log['auditable_type'])): ?>
        <?= $this->e((string) $log['auditable_type']) ?>#<?= $this->e((string) ($log['auditable_id'] ?? '')) ?>
    <?php else: ?>
        -
    <?php endif; ?>
    </td>
    <td class="text-muted"><?= $this->e((string) ($log['ip_address'] ?? '-')) ?></td>
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
    'base_url' => '/system-admin/audit-logs?' . \http_build_query([
        'site_id' => $filters['site_id'], 'event' => $filters['event'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'],
    ]) . '&',
]); ?>
<?php $this->endSection(); ?>
