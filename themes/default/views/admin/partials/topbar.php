<header class="admin-topbar">
    <button type="button" class="sidebar-toggle tooltip-bottom" data-sidebar-toggle aria-label="Đóng/Mở menu" data-tooltip="Đóng/Mở menu"><?php $this->include('admin.partials.icon', ['name' => 'menu']); ?></button>
    <div class="page-title"><?= $this->e($title ?? 'Admin') ?></div>
    <form method="GET" action="/admin/search" class="topbar-search" role="search">
        <?php $this->include('admin.partials.icon', ['name' => 'search', 'class' => 'icon topbar-search-icon']); ?>
        <input type="search" name="q" placeholder="Tìm trang, người dùng..." aria-label="Tìm kiếm toàn hệ thống" value="<?= $this->e((string) ($query ?? '')) ?>">
    </form>
    <div class="user-menu">
        <?php $unreadCount = (int) ($unread_notifications_count ?? 0); ?>
        <a href="/admin/notifications" class="notification-bell tooltip-bottom" aria-label="<?= $this->e($unreadCount > 0 ? $unreadCount . ' thông báo chưa đọc' : 'Thông báo') ?>" data-tooltip="Thông báo">
            <?php $this->include('admin.partials.icon', ['name' => 'notification']); ?>
            <?php if ($unreadCount > 0): ?>
            <span class="notification-bell-badge"><?= $this->e($unreadCount > 99 ? '99+' : (string) $unreadCount) ?></span>
            <?php endif; ?>
        </a>
        <?php $userName = $current_user_name ?? null; $userId = $current_user_id ?? null; ?>
        <div class="user-menu-dropdown" data-user-menu>
            <button type="button" class="user-menu-trigger" data-user-menu-trigger aria-haspopup="true" aria-expanded="false">
                <span class="user-name text-muted"><?= $this->e($userName ?? '') ?></span>
                <div class="user-avatar" title="<?= $this->e($userName ?? '') ?>"><?= $this->e($userName !== null && $userName !== '' ? \mb_strtoupper(\mb_substr($userName, 0, 1)) : '?') ?></div>
            </button>
            <div class="user-menu-panel" data-user-menu-panel>
                <?php if ($userId !== null): ?>
                <a href="/admin/users/<?= $this->e((string) $userId) ?>/edit" class="user-menu-item user-menu-item--link"><?php $this->include('admin.partials.icon', ['name' => 'users']); ?> Hồ sơ</a>
                <?php endif; ?>
                <a href="/admin/settings" class="user-menu-item user-menu-item--link"><?php $this->include('admin.partials.icon', ['name' => 'settings']); ?> Cài đặt</a>
                <form method="POST" action="/admin/logout">
                    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
                    <button type="submit" class="user-menu-item"><?php $this->include('admin.partials.icon', ['name' => 'logout']); ?> Đăng xuất</button>
                </form>
            </div>
        </div>
    </div>
</header>
