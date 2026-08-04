<?php $this->extend('system_admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Quan ly Site</h1>
    <a href="/system-admin/sites/create" class="btn btn-primary">+ Tao site moi</a>
</div>

<?php $this->include('admin.partials.table_filter', [
    'filter_action' => '/system-admin/sites',
    'filter_fields' => [
        ['name' => 'status', 'label' => 'Trang thai', 'type' => 'select', 'value' => $filters['status'] ?? '', 'options' => [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'maintenance', 'label' => 'Maintenance'],
            ['value' => 'suspended', 'label' => 'Suspended'],
        ]],
    ],
]); ?>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr><th>Ten Site</th><th>Domain</th><th>Theme</th><th>Trang thai</th><th>Hanh dong</th></tr>
</thead>
<tbody>
<?php foreach ($sites as $site): ?>
<tr>
    <td><?= $this->e((string) $site['name']) ?></td>
    <td>
        <?php foreach ($site['domains'] as $domain): ?>
        <span class="badge <?= $domain['is_primary'] ? 'badge-success' : 'badge-secondary' ?>"><?= $this->e((string) $domain['domain']) ?></span>
        <?php endforeach; ?>
    </td>
    <td><?= $this->e((string) ($site['theme_active'] ?? 'default')) ?></td>
    <td data-field="status">
        <span class="badge <?= $site['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= $this->e((string) $site['status']) ?></span>
    </td>
    <td>
        <div class="table-actions">
        <a href="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/edit" class="btn btn-secondary btn-sm">Sua</a>

        <?php if ($site['status'] === 'active'): ?>
        <form method="POST" action="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/suspend">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Suspend</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/system-admin/sites/<?= $this->e((string) $site['id']) ?>/activate">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Activate</button>
        </form>
        <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php $this->endSection(); ?>
