<header class="admin-topbar">
    <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Toggle menu">&#9776;</button>
    <div class="page-title"><?= $this->e($title ?? 'Admin') ?></div>
    <div class="user-menu">
        <button type="button" class="theme-toggle" data-theme-toggle aria-label="Doi giao dien Sang/Toi" title="Doi giao dien Sang/Toi">&#9788;</button>
        <?php $userName = $current_user_name ?? null; ?>
        <span class="user-name text-muted"><?= $this->e($userName ?? '') ?></span>
        <div class="user-avatar" title="<?= $this->e($userName ?? '') ?>"><?= $this->e($userName !== null && $userName !== '' ? \mb_strtoupper(\mb_substr($userName, 0, 1)) : '?') ?></div>
    </div>
</header>
