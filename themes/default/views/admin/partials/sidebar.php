<?php
/**
 * Sidebar Admin - moi muc co icon (admin.partials.icon) + nhan tieng Viet co dau. Active-state
 * (class "is-active") xac dinh qua $current_path (Controller truyen $request->path(), fallback
 * '' neu chua truyen - khong active muc nao, khong loi bien chua dinh nghia).
 */
$currentPath = $current_path ?? '';

$isActive = static function (string $prefix) use ($currentPath): bool {
    return $currentPath === $prefix || \str_starts_with($currentPath, $prefix . '/');
};
?>
<nav class="admin-nav">
    <div class="nav-group-label">Menu chính</div>
    <a href="/admin/dashboard"<?= $isActive('/admin/dashboard') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'dashboard']); ?><span>Bảng điều khiển</span></span>
    </a>
    <a href="/admin/notifications"<?= $isActive('/admin/notifications') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'notification']); ?><span>Thông báo</span></span>
        <?php if (($unread_notifications_count ?? 0) > 0): ?> <span class="badge badge-danger"><?= $this->e((string) $unread_notifications_count) ?></span><?php endif; ?>
    </a>
    <a href="/admin/users"<?= $isActive('/admin/users') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'users']); ?><span>Người dùng</span></span>
    </a>
    <a href="/admin/roles"<?= $isActive('/admin/roles') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'roles']); ?><span>Vai trò &amp; Phân quyền</span></span>
    </a>

    <div class="nav-group-label">Nội dung</div>
    <a href="/admin/pages"<?= $isActive('/admin/pages') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'pages']); ?><span>Trang nội dung</span></span>
    </a>
    <a href="/admin/media"<?= $isActive('/admin/media') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'media']); ?><span>Media</span></span>
    </a>
    <a href="/admin/menus"<?= $isActive('/admin/menus') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'menu']); ?><span>Menu điều hướng</span></span>
    </a>
    <a href="/admin/seo"<?= $isActive('/admin/seo') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'seo']); ?><span>SEO</span></span>
    </a>
    <a href="/admin/comments"<?= $isActive('/admin/comments') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'comments']); ?><span>Bình luận</span></span>
    </a>

    <div class="nav-group-label">Hệ thống</div>
    <a href="/admin/settings"<?= $isActive('/admin/settings') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'settings']); ?><span>Cài đặt chung</span></span>
    </a>
    <a href="/admin/system-settings"<?= $isActive('/admin/system-settings') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'system-settings']); ?><span>Cấu hình hệ thống</span></span>
    </a>
    <a href="/admin/audit-logs"<?= $isActive('/admin/audit-logs') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'audit-log']); ?><span>Nhật ký hoạt động</span></span>
    </a>
    <a href="/admin/plugins"<?= $isActive('/admin/plugins') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'plugins']); ?><span>Plugin</span></span>
    </a>

    <?php if (!empty($extra_admin_menu_items ?? [])): ?>
    <div class="nav-group-label">Mở rộng</div>
    <?php foreach ($extra_admin_menu_items as $item): ?>
    <a href="<?= $this->e((string) ($item['url'] ?? '#')) ?>"<?= $isActive((string) ($item['url'] ?? '#')) ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => (string) ($item['icon'] ?? 'ecommerce')]); ?><span><?= $this->e((string) ($item['label'] ?? '')) ?></span></span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</nav>
