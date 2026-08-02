<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Audit Log</h1>
<p class="text-muted">Nhat ky hoat dong quan tri - <?= $this->e((string) $total) ?> ban ghi phu hop bo loc.</p>

<form method="GET" action="/admin/audit-logs" class="card flex gap-3" style="flex-wrap: wrap; align-items: flex-end; margin-bottom: var(--space-4);">
    <div class="field">
        <label for="event">Su kien</label>
        <select id="event" name="event">
            <option value="">-- Tat ca --</option>
            <?php foreach ($available_events as $eventOption): ?>
            <option value="<?= $this->e($eventOption) ?>"<?= $eventOption === $filters['event'] ? ' selected' : '' ?>><?= $this->e($eventOption) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="date_from">Tu ngay</label>
        <input type="date" id="date_from" name="date_from" value="<?= $this->e($filters['date_from']) ?>">
    </div>
    <div class="field">
        <label for="date_to">Den ngay</label>
        <input type="date" id="date_to" name="date_to" value="<?= $this->e($filters['date_to']) ?>">
    </div>
    <div class="field">
        <button type="submit" class="btn btn-primary">Loc</button>
        <a href="/admin/audit-logs" class="btn btn-secondary">Xoa loc</a>
    </div>
</form>

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

<?php if ($total_pages > 1): ?>
<div class="flex gap-2" style="margin-top: var(--space-4);">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <a href="/admin/audit-logs?<?= $this->e(\http_build_query(['event' => $filters['event'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'page' => $p])) ?>"
       class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $this->e((string) $p) ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
