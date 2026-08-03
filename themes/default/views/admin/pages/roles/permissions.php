<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quan ly quyen - <?= $this->e($role['name'] ?? '') ?></h1>

<?php
/**
 * Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055): gop 2 danh sach (assigned/unassigned) cu
 * thanh 1 bang Matrix duy nhat (Permission x Trang thai) - de doc hon, cung du lieu Controller da
 * truyen san (KHONG doi RoleShowPermissionsController.php, khong doi endpoint POST cua form "Gan"
 * - dung Owner Decision da duyet: chi doi hien thi). Day KHONG phai Matrix nhieu-Role vi Controller
 * hien chi truy van 1 role/lan (xem RoleShowPermissionsController::handle()) - lam Matrix that
 * nhieu-Role can Controller doc them permission cua cac role khac, ngoai pham vi "chi sua View" da
 * khoa cho Phase 18.
 */
$allRows = [];
foreach ($assigned as $permission) {
    $allRows[] = ['permission' => $permission, 'is_assigned' => true];
}
foreach ($unassigned as $permission) {
    $allRows[] = ['permission' => $permission, 'is_assigned' => false];
}
\usort($allRows, static fn (array $a, array $b): int => \strcmp((string) $a['permission']['key'], (string) $b['permission']['key']));
?>

<div class="card">
<?php if (empty($allRows)): ?>
<p class="text-muted mb-0">He thong chua co permission nao.</p>
<?php else: ?>
<div class="permission-matrix-wrap">
<table class="permission-matrix">
<thead>
<tr><th>Permission</th><th class="permission-cell">Trang thai</th><th class="permission-cell">Thao tac</th></tr>
</thead>
<tbody>
<?php foreach ($allRows as $row): ?>
<?php $permission = $row['permission']; ?>
<tr>
    <td><code><?= $this->e((string) $permission['key']) ?></code></td>
    <td class="permission-cell">
    <?php if ($row['is_assigned']): ?>
    <span class="permission-granted" aria-label="Da gan">&check;</span>
    <?php else: ?>
    <span class="text-muted">&mdash;</span>
    <?php endif; ?>
    </td>
    <td class="permission-cell">
    <?php if ($row['is_assigned']): ?>
    <span class="badge badge-success">Da gan</span>
    <?php elseif ($isSystem): ?>
    <span class="text-muted" style="font-size:12px;">System role</span>
    <?php else: ?>
    <form method="POST" action="/admin/roles/<?= $this->e((string) $role['id']) ?>/permissions" class="permission-grant-form">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <input type="hidden" name="permission_id" value="<?= $this->e((string) $permission['id']) ?>">
        <button type="submit" class="btn btn-secondary btn-sm">Gan</button>
    </form>
    <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php if ($isSystem): ?>
<p class="text-muted" style="margin-top: var(--space-4); margin-bottom:0;">System role khong the sua permission.</p>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
