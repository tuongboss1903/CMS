<?php
/**
 * Empty State chuyen nghiep dung chung (Design Audit Phase 24) - danh cho KHOI RIENG (thay the
 * toan bo bang/luoi khi rong), khac voi class ".empty-state" cu trong tailwind.css (van giu
 * nguyen, chi dung cho 1 O <td> rong trong bang co san). Goi qua:
 *   $this->include('admin.partials.empty_state', [
 *       'icon' => 'media',                       // ten icon trong _icon_paths.php
 *       'title' => 'Chưa có file nào',
 *       'description' => '...',                  // tuy chon
 *       'action' => ['label' => '...', 'href' => '/...']            // link, HOAC
 *                 ['label' => '...', 'modal' => 'upload-modal']     // mo modal co san, tuy chon
 *   ]);
 * Khong co 'action' -> chi hien icon + tieu de + mo ta (dung cho man hinh khong co hanh dong
 * "tao moi" hop ly, vd Thong bao/Plugin - du lieu sinh tu he thong, khong phai admin bam tao).
 */
$icon = $icon ?? 'search';
$title = $title ?? '';
$description = $description ?? null;
$action = $action ?? null;
?>
<div class="empty-state-pro">
    <div class="empty-state-pro-icon"><?php $this->include('admin.partials.icon', ['name' => $icon]); ?></div>
    <h3 class="empty-state-pro-title"><?= $this->e((string) $title) ?></h3>
    <?php if ($description !== null): ?>
    <p class="empty-state-pro-desc"><?= $this->e((string) $description) ?></p>
    <?php endif; ?>
    <?php if ($action !== null): ?>
    <?php if (isset($action['modal'])): ?>
    <button type="button" class="btn btn-primary" data-modal-open="<?= $this->e((string) $action['modal']) ?>">
        <?php if (!empty($action['icon'])): ?><?php $this->include('admin.partials.icon', ['name' => (string) $action['icon']]); ?><?php endif; ?>
        <?= $this->e((string) $action['label']) ?>
    </button>
    <?php else: ?>
    <a href="<?= $this->e((string) ($action['href'] ?? '#')) ?>" class="btn btn-primary">
        <?php if (!empty($action['icon'])): ?><?php $this->include('admin.partials.icon', ['name' => (string) $action['icon']]); ?><?php endif; ?>
        <?= $this->e((string) $action['label']) ?>
    </a>
    <?php endif; ?>
    <?php endif; ?>
</div>
