<header class="admin-topbar">
    <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Đóng/Mở menu"><?php $this->include('admin.partials.icon', ['name' => 'menu']); ?></button>
    <div class="page-title"><?= $this->e($title ?? 'Admin') ?></div>
    <div class="user-menu">
        <button type="button" class="theme-toggle" data-theme-toggle aria-label="Đổi giao diện Sáng/Tối" title="Đổi giao diện Sáng/Tối">
            <span class="theme-icon-dark"><?php $this->include('admin.partials.icon', ['name' => 'moon']); ?></span>
            <span class="theme-icon-light"><?php $this->include('admin.partials.icon', ['name' => 'sun']); ?></span>
        </button>
        <?php $userName = $current_user_name ?? null; ?>
        <span class="user-name text-muted"><?= $this->e($userName ?? '') ?></span>
        <div class="user-avatar" title="<?= $this->e($userName ?? '') ?>"><?= $this->e($userName !== null && $userName !== '' ? \mb_strtoupper(\mb_substr($userName, 0, 1)) : '?') ?></div>
    </div>
</header>
