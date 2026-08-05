<header class="admin-topbar">
    <button type="button" class="sidebar-toggle tooltip-bottom" data-sidebar-toggle aria-label="Đóng/Mở menu" data-tooltip="Đóng/Mở menu"><?php $this->include('admin.partials.icon', ['name' => 'menu']); ?></button>
    <div class="page-title"><?= $this->e($title ?? 'Admin') ?></div>
    <div class="user-menu">
        <button type="button" class="theme-toggle tooltip-bottom" data-theme-toggle aria-label="Đổi giao diện Sáng/Tối" data-tooltip="Đổi giao diện Sáng/Tối">
            <span class="theme-icon-dark"><?php $this->include('admin.partials.icon', ['name' => 'moon']); ?></span>
            <span class="theme-icon-light"><?php $this->include('admin.partials.icon', ['name' => 'sun']); ?></span>
        </button>
        <?php $userName = $current_user_name ?? null; ?>
        <div class="user-menu-dropdown" data-user-menu>
            <button type="button" class="user-menu-trigger" data-user-menu-trigger aria-haspopup="true" aria-expanded="false">
                <span class="user-name text-muted"><?= $this->e($userName ?? '') ?></span>
                <div class="user-avatar" title="<?= $this->e($userName ?? '') ?>"><?= $this->e($userName !== null && $userName !== '' ? \mb_strtoupper(\mb_substr($userName, 0, 1)) : '?') ?></div>
            </button>
            <div class="user-menu-panel" data-user-menu-panel>
                <form method="POST" action="/admin/logout">
                    <input type="hidden" name="_token" value="<?= $this->e($csrf_token ?? '') ?>">
                    <button type="submit" class="user-menu-item"><?php $this->include('admin.partials.icon', ['name' => 'logout']); ?> Đăng xuất</button>
                </form>
            </div>
        </div>
    </div>
</header>
