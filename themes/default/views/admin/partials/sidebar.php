<nav class="admin-nav">
    <div class="nav-group-label">Menu chinh</div>
    <a href="/admin/dashboard">Dashboard</a>
    <a href="/admin/notifications">Thong bao<?php if (($unread_notifications_count ?? 0) > 0): ?> <span class="badge badge-danger"><?= $this->e((string) $unread_notifications_count) ?></span><?php endif; ?></a>
    <a href="/admin/users">Users</a>
    <a href="/admin/roles">Roles</a>

    <div class="nav-group-label">Noi dung</div>
    <a href="/admin/pages">Pages</a>
    <a href="/admin/media">Media</a>
    <a href="/admin/menus">Menu</a>
    <a href="/admin/seo">SEO</a>
    <a href="/admin/comments">Comments</a>

    <div class="nav-group-label">He thong</div>
    <a href="/admin/settings">Cai dat chung</a>
    <a href="/admin/system-settings">System Settings</a>
    <a href="/admin/audit-logs">Audit Log</a>
    <a href="/admin/plugins">Plugins</a>

    <?php if (!empty($extra_admin_menu_items ?? [])): ?>
    <div class="nav-group-label">Plugin</div>
    <?php foreach ($extra_admin_menu_items as $item): ?>
    <a href="<?= $this->e((string) ($item['url'] ?? '#')) ?>"><?= $this->e((string) ($item['label'] ?? '')) ?></a>
    <?php endforeach; ?>
    <?php endif; ?>
</nav>
