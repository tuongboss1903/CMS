<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Audit Log</h1>
<p class="text-muted">Nhat ky hoat dong quan tri - <?= $this->e((string) $total) ?> ban ghi phu hop bo loc.</p>

<?php
/**
 * Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055): dung partial table_filter/pagination dung
 * chung thay vi viet tay (Phase 16) - GIU NGUYEN ten query param (event/date_from/date_to/page)
 * va class CSS (badge badge-neutral, empty-state...) de khong vo test cu (AdminAuditLogTest.php).
 */
$eventOptions = \array_map(
    static fn (string $eventOption): array => ['value' => $eventOption, 'label' => $eventOption],
    $available_events
);
?>
<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/admin/audit-logs',
    'filter_fields' => [
        ['name' => 'event', 'label' => 'Su kien', 'type' => 'select', 'value' => $filters['event'], 'options' => $eventOptions],
        ['name' => 'date_from', 'label' => 'Tu ngay', 'type' => 'date', 'value' => $filters['date_from']],
        ['name' => 'date_to', 'label' => 'Den ngay', 'type' => 'date', 'value' => $filters['date_to']],
    ],
]); ?>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Thoi gian</th><th>Su kien</th><th>User ID</th><th>Doi tuong</th><th>IP</th><th>Chi tiet</th></tr>
</thead>
<tbody>
<?php foreach ($logs as $log): ?>
<tr>
    <td class="text-muted"><?= $this->e((string) $log['created_at']) ?></td>
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
    <td>
    <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
        <details>
        <summary>Xem</summary>
        <?php if (!empty($log['old_values'])): ?>
        <div><strong>Truoc:</strong> <code><?= $this->e((string) $log['old_values']) ?></code></div>
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
<tr><td colspan="6" class="empty-state">Khong co nhat ky nao phu hop.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<?php $this->include('admin.partials.pagination', [
    'page' => $page,
    'total_pages' => $total_pages,
    'base_url' => '/admin/audit-logs?' . \http_build_query(['event' => $filters['event'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']]) . '&',
]); ?>
<?php $this->endSection(); ?>
