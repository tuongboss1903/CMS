<?php
/**
 * Sidebar System Admin - cung pattern admin.partials.sidebar (icon + active-state theo
 * $current_path). Dung chung partial admin.partials.icon (khong nhan biet namespace goc, chi la
 * duong dan template) - khong tao ban sao icon set rieng cho System Admin.
 */
$currentPath = $current_path ?? '';

$isActive = static function (string $prefix) use ($currentPath): bool {
    return $currentPath === $prefix || \str_starts_with($currentPath, $prefix . '/');
};
?>
<nav class="admin-nav">
    <div class="nav-group-label">Nền tảng</div>
    <a href="/system-admin/dashboard"<?= $isActive('/system-admin/dashboard') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'dashboard']); ?><span>Bảng điều khiển</span></span>
    </a>
    <a href="/system-admin/sites"<?= $isActive('/system-admin/sites') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'server']); ?><span>Site / Tenant</span></span>
    </a>
    <a href="/system-admin/modules"<?= $isActive('/system-admin/modules') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'plugins']); ?><span>Module</span></span>
    </a>
    <a href="/system-admin/themes"<?= $isActive('/system-admin/themes') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'palette']); ?><span>Theme</span></span>
    </a>
    <a href="/system-admin/plans"<?= $isActive('/system-admin/plans') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'billing']); ?><span>Gói dịch vụ</span></span>
    </a>

    <div class="nav-group-label">Nhật ký</div>
    <a href="/system-admin/audit-logs"<?= $isActive('/system-admin/audit-logs') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'audit-log']); ?><span>Nhật ký theo Site</span></span>
    </a>
    <a href="/system-admin/platform-audit-logs"<?= $isActive('/system-admin/platform-audit-logs') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'audit-log']); ?><span>Nhật ký Super Admin</span></span>
    </a>
</nav>
