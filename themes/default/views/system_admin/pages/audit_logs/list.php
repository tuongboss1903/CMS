<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Audit Log (xuyen site)</h1>
<p class="text-muted">Nhat ky hoat dong cua Site Admin/User tren TAT CA site - <?= $this->e((string) $total) ?> ban ghi phu hop bo loc.</p>

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
        ['name' => 'event', 'label' => 'Su kien', 'type' => 'select', 'value' => $filters['event'], 'options' => $eventOptions],
        ['name' => 'date_from', 'label' => 'Tu ngay', 'type' => 'date', 'value' => $filters['date_from']],
        ['name' => 'date_to', 'label' => 'Den ngay', 'type' => 'date', 'value' => $filters['date_to']],
    ],
]); ?>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Thoi gian</th><th>Site</th><th>Su kien</th><th>User ID</th><th>Doi tuong</th><th>IP</th></tr>
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
<tr><td colspan="6" class="empty-state">Khong co nhat ky nao phu hop.</td></tr>
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
