<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Quản lý quyền — <?= $this->e($role['name'] ?? '') ?></h1>

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

/**
 * Nhom theo module (tien to truoc dau "." trong permission key, vd "page.view" -> "page") - thuan
 * display logic trong View, khong doi Controller/query. Design Roles yeu cau nhom theo module thay
 * vi 1 bang phang de giam cognitive load.
 *
 * @param list<array{permission: array<string, mixed>, is_assigned: bool}> $rows
 * @return array<string, list<array{permission: array<string, mixed>, is_assigned: bool}>>
 */
$groupByModule = static function (array $rows): array {
    $groups = [];

    foreach ($rows as $row) {
        $key = (string) $row['permission']['key'];
        $module = \str_contains($key, '.') ? \substr($key, 0, \strpos($key, '.')) : $key;
        $groups[$module][] = $row;
    }

    \ksort($groups);

    return $groups;
};

$groups = $groupByModule($allRows);
?>

<div class="card">
<?php if (empty($allRows)): ?>
<p class="text-muted mb-0">Hệ thống chưa có permission nào.</p>
<?php else: ?>
<?php foreach ($groups as $module => $rows): ?>
<fieldset class="permission-group">
    <legend><?= $this->e(\ucfirst($module)) ?></legend>
    <?php foreach ($rows as $row): ?>
    <?php $permission = $row['permission']; ?>
    <div class="permission-group-row">
        <div class="permission-group-label">
            <span><?= $this->e((string) ($permission['description'] ?? $permission['key'])) ?></span>
            <code class="text-muted"><?= $this->e((string) $permission['key']) ?></code>
        </div>
        <div class="permission-group-action">
        <?php if ($row['is_assigned'] && $isSystem): ?>
        <span class="badge badge-success">Đã gán</span>
        <?php elseif ($row['is_assigned']): ?>
        <form method="POST" action="/admin/roles/<?= $this->e((string) $role['id']) ?>/permissions/<?= $this->e((string) $permission['id']) ?>/delete" class="permission-grant-form" data-confirm="Gỡ quyền này khỏi vai trò?">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'x']); ?> Gỡ</button>
        </form>
        <?php elseif ($isSystem): ?>
        <span class="text-muted" style="font-size:12px;">Vai trò hệ thống</span>
        <?php else: ?>
        <form method="POST" action="/admin/roles/<?= $this->e((string) $role['id']) ?>/permissions" class="permission-grant-form">
            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
            <input type="hidden" name="permission_id" value="<?= $this->e((string) $permission['id']) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'check']); ?> Gán</button>
        </form>
        <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</fieldset>
<?php endforeach; ?>
<?php endif; ?>
<?php if ($isSystem): ?>
<p class="text-muted mt-4 mb-0">Vai trò hệ thống không thể sửa quyền.</p>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
