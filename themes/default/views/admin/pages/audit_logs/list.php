<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Nhật ký hoạt động</h1>
<p class="text-muted">Nhật ký hoạt động quản trị — <?= $this->e((string) $total) ?> bản ghi phù hợp bộ lọc.</p>

<?php
/**
 * Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055): dung partial table_filter/pagination dung
 * chung thay vi viet tay (Phase 16) - GIU NGUYEN ten query param (event/date_from/date_to/page)
 * va class CSS (badge badge-neutral, empty-state...) de khong vo test cu (AdminAuditLogTest.php).
 */
$eventOptions = \array_map(
    static fn (string $eventOption): array => ['value' => $eventOption, 'label' => \Modules\Admin\AuditLogPresenter::eventLabel($eventOption)],
    $available_events
);
?>
<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/admin/audit-logs',
    'filter_fields' => [
        ['name' => 'event', 'label' => 'Sự kiện', 'type' => 'select', 'value' => $filters['event'], 'options' => $eventOptions],
        ['name' => 'date_from', 'label' => 'Từ ngày', 'type' => 'date', 'value' => $filters['date_from']],
        ['name' => 'date_to', 'label' => 'Đến ngày', 'type' => 'date', 'value' => $filters['date_to']],
    ],
]); ?>

<div class="table-wrap table-wrap--flat">
<table class="data-table">
<thead>
<tr><th scope="col">Thời gian</th><th scope="col">Sự kiện</th><th scope="col">Người dùng</th><th scope="col">Đối tượng</th><th scope="col">Nguồn truy cập</th><th scope="col">Chi tiết</th></tr>
</thead>
<tbody>
<?php foreach ($logs as $log): ?>
<?php $userName = (string) ($log['user_name'] ?? ''); ?>
<tr>
    <td class="text-muted" style="font-family:var(--font-mono);font-size:var(--text-xs);"><?= $this->e((string) $log['created_at']) ?></td>
    <td><span class="badge <?= $this->e(\Modules\Admin\AuditLogPresenter::eventBadgeClass((string) $log['event'])) ?>" title="<?= $this->e((string) $log['event']) ?>"><?= $this->e(\Modules\Admin\AuditLogPresenter::eventLabel((string) $log['event'])) ?></span></td>
    <td>
    <?php if ($userName !== ''): ?>
        <div class="user-cell">
            <div class="user-cell-avatar"><?= $this->e(\mb_strtoupper(\mb_substr($userName, 0, 1))) ?></div>
            <span class="text-truncate" title="<?= $this->e($userName) ?>"><?= $this->e($userName) ?></span>
        </div>
    <?php else: ?>
        <span class="text-muted">—</span>
    <?php endif; ?>
    </td>
    <td>
    <?php if (!empty($log['auditable_type'])): ?>
        <?= $this->e((string) $log['auditable_type']) ?>#<?= $this->e((string) ($log['auditable_id'] ?? '')) ?>
    <?php else: ?>
        <span class="text-muted">—</span>
    <?php endif; ?>
    </td>
    <td class="text-muted"><?= $this->e(\Modules\Admin\AuditLogPresenter::ipLabel($log['ip_address'] ?? null)) ?></td>
    <td>
    <?php if (!empty($log['old_values']) || !empty($log['new_values']) || !empty($log['ip_address'])): ?>
        <details>
        <summary>Xem</summary>
        <?php if (!empty($log['ip_address'])): ?>
        <div><strong>IP:</strong> <code><?= $this->e((string) $log['ip_address']) ?></code></div>
        <?php endif; ?>
        <?php if (!empty($log['old_values'])): ?>
        <div><strong>Trước:</strong> <code><?= $this->e((string) $log['old_values']) ?></code></div>
        <?php endif; ?>
        <?php if (!empty($log['new_values'])): ?>
        <div><strong>Sau:</strong> <code><?= $this->e((string) $log['new_values']) ?></code></div>
        <?php endif; ?>
        </details>
    <?php else: ?>
        <span class="text-muted">—</span>
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
    'base_url' => '/admin/audit-logs?' . \http_build_query(['event' => $filters['event'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']]) . '&',
]); ?>
<?php $this->endSection(); ?>
