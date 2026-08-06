<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<h1>Kết quả tìm kiếm</h1>
<?php if ($query === ''): ?>
<p class="text-muted">Nhập từ khoá vào ô tìm kiếm ở thanh trên để bắt đầu.</p>
<?php else: ?>
<p class="text-muted">Kết quả cho "<?= $this->e($query) ?>" — <?= $this->e((string) (\count($pages) + \count($users))) ?> mục phù hợp.</p>

<?php if (empty($pages) && empty($users)): ?>
<?php $this->include('admin.partials.empty_state', [
    'icon' => 'search',
    'title' => 'Không tìm thấy kết quả nào',
    'description' => 'Thử một từ khoá khác, hoặc kiểm tra lại chính tả.',
]); ?>
<?php else: ?>

<?php if (!empty($pages)): ?>
<div class="card mb-5">
    <h2 style="font-size:16px;">Trang nội dung</h2>
    <ul class="search-result-list">
    <?php foreach ($pages as $page): ?>
    <li>
        <a href="/admin/pages/<?= $this->e((string) $page['id']) ?>/edit" class="search-result-item">
            <?php $this->include('admin.partials.icon', ['name' => 'pages']); ?>
            <span>
                <span class="search-result-title"><?= $this->e((string) $page['title']) ?></span>
                <span class="text-muted search-result-meta"><?= $this->e((string) $page['slug']) ?></span>
            </span>
        </a>
    </li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($users)): ?>
<div class="card">
    <h2 style="font-size:16px;">Người dùng</h2>
    <ul class="search-result-list">
    <?php foreach ($users as $user): ?>
    <li>
        <a href="/admin/users/<?= $this->e((string) $user['id']) ?>/edit" class="search-result-item">
            <?php $this->include('admin.partials.icon', ['name' => 'users']); ?>
            <span>
                <span class="search-result-title"><?= $this->e((string) $user['name']) ?></span>
                <span class="text-muted search-result-meta"><?= $this->e((string) $user['email']) ?></span>
            </span>
        </a>
    </li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>
<?php $this->endSection(); ?>
