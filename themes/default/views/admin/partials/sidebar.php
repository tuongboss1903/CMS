<?php
/**
 * Sidebar Admin - moi muc co icon (admin.partials.icon) + nhan tieng Viet co dau. Active-state
 * (class "is-active") xac dinh qua $current_path (Controller truyen $request->path(), fallback
 * '' neu chua truyen - khong active muc nao, khong loi bien chua dinh nghia).
 *
 * Design Audit Phase 10: nhan chu ("nav-label") an di o bac rail icon-only (901-1200px, xem
 * resources/admin/tailwind.css) - title="..." tren <a> lam tooltip trinh duyet thay the, KHONG
 * dung aria-label (se ghi de accessible name ngay ca khi nhan chu con hien - vi pham WCAG 2.5.3
 * Label in Name). title chi bo sung, khong thay the accessible name khi da co text con.
 */
$currentPath = $current_path ?? '';

$isActive = static function (string $prefix) use ($currentPath): bool {
    return $currentPath === $prefix || \str_starts_with($currentPath, $prefix . '/');
};
?>
<nav class="admin-nav">
    <div class="nav-group-label">Menu chính</div>
    <a href="/admin/dashboard" title="Bảng điều khiển"<?= $isActive('/admin/dashboard') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'dashboard']); ?><span class="nav-label">Bảng điều khiển</span></span>
    </a>
    <a href="/admin/notifications" title="Thông báo"<?= $isActive('/admin/notifications') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'notification']); ?><span class="nav-label">Thông báo</span></span>
        <?php if (($unread_notifications_count ?? 0) > 0): ?> <span class="badge badge-danger"><?= $this->e((string) $unread_notifications_count) ?></span><?php endif; ?>
    </a>
    <a href="/admin/users" title="Người dùng"<?= $isActive('/admin/users') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'users']); ?><span class="nav-label">Người dùng</span></span>
    </a>
    <a href="/admin/roles" title="Vai trò &amp; Phân quyền"<?= $isActive('/admin/roles') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'roles']); ?><span class="nav-label">Vai trò &amp; Phân quyền</span></span>
    </a>

    <div class="nav-group-label">Nội dung</div>
    <a href="/admin/pages" title="Trang nội dung"<?= $isActive('/admin/pages') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'pages']); ?><span class="nav-label">Trang nội dung</span></span>
    </a>
    <a href="/admin/media" title="Media"<?= $isActive('/admin/media') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'media']); ?><span class="nav-label">Media</span></span>
    </a>
    <a href="/admin/menus" title="Menu điều hướng"<?= $isActive('/admin/menus') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'menu']); ?><span class="nav-label">Menu điều hướng</span></span>
    </a>
    <a href="/admin/seo" title="SEO"<?= $isActive('/admin/seo') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'seo']); ?><span class="nav-label">SEO</span></span>
    </a>
    <a href="/admin/comments" title="Bình luận"<?= $isActive('/admin/comments') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'comments']); ?><span class="nav-label">Bình luận</span></span>
    </a>

    <div class="nav-group-label">Hệ thống</div>
    <a href="/admin/settings" title="Cài đặt chung"<?= $isActive('/admin/settings') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'settings']); ?><span class="nav-label">Cài đặt chung</span></span>
    </a>
    <a href="/admin/system-settings" title="Cấu hình hệ thống"<?= $isActive('/admin/system-settings') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'system-settings']); ?><span class="nav-label">Cấu hình hệ thống</span></span>
    </a>
    <a href="/admin/audit-logs" title="Nhật ký hoạt động"<?= $isActive('/admin/audit-logs') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'audit-log']); ?><span class="nav-label">Nhật ký hoạt động</span></span>
    </a>
    <a href="/admin/plugins" title="Plugin"<?= $isActive('/admin/plugins') ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => 'plugins']); ?><span class="nav-label">Plugin</span></span>
    </a>

    <?php if (!empty($extra_admin_menu_items ?? [])): ?>
    <div class="nav-group-label">Mở rộng</div>
    <?php foreach ($extra_admin_menu_items as $item): ?>
    <a href="<?= $this->e((string) ($item['url'] ?? '#')) ?>" title="<?= $this->e((string) ($item['label'] ?? '')) ?>"<?= $isActive((string) ($item['url'] ?? '#')) ? ' class="is-active"' : '' ?>>
        <span class="nav-link-inner"><?php $this->include('admin.partials.icon', ['name' => (string) ($item['icon'] ?? 'ecommerce')]); ?><span class="nav-label"><?= $this->e((string) ($item['label'] ?? '')) ?></span></span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</nav>
