<?php $this->extend('admin.layouts.main'); ?>
<?php $this->section('content'); ?>
<div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
    <h1 class="mb-0">Menu: <?= $this->e($menu['name']) ?></h1>
    <a href="/admin/menus" class="btn btn-secondary">Quay lại danh sách</a>
</div>

<div class="card" style="margin-bottom: var(--space-5);">
    <form method="POST" action="/admin/menus/<?= $this->e((string) $menu['id']) ?>" class="flex gap-3" style="flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <div class="field mb-0" style="flex:1; min-width:200px;">
            <label for="name">Tên Menu</label>
            <input type="text" id="name" name="name" value="<?= $this->e($menu['name']) ?>">
        </div>
        <div class="field mb-0" style="flex:1; min-width:160px;">
            <label for="location_key">Vị trí hiển thị (location key)</label>
            <input type="text" id="location_key" name="location_key" value="<?= $this->e($menu['location_key']) ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Lưu Menu</button>
    </form>
</div>

<?php
/**
 * Closure cuc bo (khong phai function toan cuc) - View::renderTemplate() dung include() (khong
 * include_once), khai bao function/class o top-level file view se Fatal "Cannot redeclare" khi
 * view nay duoc render lan 2 trong cung 1 tien trinh PHP (chac chan xay ra qua nhieu test method
 * PHPUnit). Closure la bien cuc bo, tao lai moi lan goi include - an toan.
 *
 * @param list<array<string, mixed>> $nodes
 * @return list<array{id: int, label: string, depth: int}>
 */
$flatten = function (array $nodes, int $depth = 0) use (&$flatten): array {
    $result = [];

    foreach ($nodes as $node) {
        $result[] = ['id' => (int) $node['id'], 'label' => (string) $node['label'], 'depth' => $depth];
        $result = [...$result, ...$flatten($node['children'] ?? [], $depth + 1)];
    }

    return $result;
};
?>

<div class="card" style="margin-bottom: var(--space-5);">
    <h2 style="font-size:16px;">Thêm Menu Item</h2>
    <form method="POST" action="/admin/menus/<?= $this->e((string) $menu['id']) ?>/items" class="flex gap-3" style="flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
        <div class="field mb-0" style="flex:1; min-width:160px;">
            <label for="item-label">Nhãn</label>
            <input type="text" id="item-label" name="label">
        </div>
        <div class="field mb-0">
            <label for="item-type">Loại</label>
            <select id="item-type" name="type" onchange="document.getElementById('item-page-field').style.display = this.value === 'page' ? 'block' : 'none'; document.getElementById('item-url-field').style.display = this.value === 'custom' ? 'block' : 'none';">
                <option value="page">Trang nội dung</option>
                <option value="custom">URL tuỳ chỉnh</option>
            </select>
        </div>
        <div class="field mb-0" id="item-page-field">
            <label for="item-reference-id">Trang</label>
            <select id="item-reference-id" name="reference_id">
                <?php foreach ($pages as $page): ?>
                <option value="<?= $this->e((string) $page['id']) ?>"><?= $this->e($page['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field mb-0" id="item-url-field" style="display:none;">
            <label for="item-url">URL</label>
            <input type="text" id="item-url" name="url" placeholder="https://...">
        </div>
        <div class="field mb-0">
            <label for="item-parent-id">Mục cha (tuỳ chọn)</label>
            <select id="item-parent-id" name="parent_id">
                <option value="">-- Cấp gốc --</option>
                <?php foreach ($flatten($tree) as $flatItem): ?>
                <option value="<?= $this->e((string) $flatItem['id']) ?>"><?= \str_repeat('-- ', $flatItem['depth']) ?><?= $this->e($flatItem['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'plus']); ?> Thêm</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:16px;">Cấu trúc Menu (kéo-thả để sắp xếp)</h2>
    <ul class="menu-tree" data-menu-tree data-menu-id="<?= $this->e((string) $menu['id']) ?>" data-csrf-token="<?= $this->e($csrf_token) ?>">
        <?php
        $renderNode = function (array $node) use (&$renderNode, $pages, $csrf_token): void {
            $itemId = (int) $node['id'];
            $modalId = 'edit-item-' . $itemId;
            $pageFieldId = 'edit-item-page-field-' . $itemId;
            $urlFieldId = 'edit-item-url-field-' . $itemId;
            ?>
            <li class="menu-tree-item" draggable="true" data-item-id="<?= $itemId ?>">
                <div class="menu-tree-item-row">
                    <span class="drag-handle" aria-hidden="true">::</span>
                    <strong><?= $this->e($node['label']) ?></strong>
                    <span class="badge badge-neutral"><?= $this->e($node['type']) ?></span>
                    <button type="button" class="btn btn-secondary btn-sm" data-modal-open="<?= $modalId ?>"><?php $this->include('admin.partials.icon', ['name' => 'edit']); ?> Sửa</button>
                    <form method="POST" action="/admin/menu-items/<?= $itemId ?>/delete" data-confirm="Xoá mục này và toàn bộ mục con?">
                        <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
                        <button type="submit" class="btn btn-danger btn-sm"><?php $this->include('admin.partials.icon', ['name' => 'trash']); ?> Xoá</button>
                    </form>
                </div>

                <div class="modal-overlay" id="<?= $modalId ?>">
                    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="<?= $modalId ?>-title" tabindex="-1">
                        <h2 id="<?= $modalId ?>-title">Sửa Menu Item</h2>
                        <form method="POST" action="/admin/menu-items/<?= $itemId ?>">
                            <input type="hidden" name="_token" value="<?= $this->e($csrf_token) ?>">
                            <div class="field">
                                <label for="<?= $modalId ?>-label">Nhãn</label>
                                <input type="text" id="<?= $modalId ?>-label" name="label" value="<?= $this->e($node['label']) ?>">
                            </div>
                            <div class="field">
                                <label for="<?= $modalId ?>-type">Loại</label>
                                <select id="<?= $modalId ?>-type" name="type" onchange="document.getElementById('<?= $pageFieldId ?>').style.display = this.value === 'page' ? 'block' : 'none'; document.getElementById('<?= $urlFieldId ?>').style.display = this.value === 'custom' ? 'block' : 'none';">
                                    <option value="page" <?= $node['type'] === 'page' ? 'selected' : '' ?>>Trang nội dung</option>
                                    <option value="custom" <?= $node['type'] === 'custom' ? 'selected' : '' ?>>URL tuỳ chỉnh</option>
                                </select>
                            </div>
                            <div class="field" id="<?= $pageFieldId ?>" style="<?= $node['type'] === 'page' ? '' : 'display:none;' ?>">
                                <label for="<?= $modalId ?>-reference">Trang</label>
                                <select id="<?= $modalId ?>-reference" name="reference_id">
                                    <?php foreach ($pages as $page): ?>
                                    <option value="<?= $this->e((string) $page['id']) ?>" <?= (string) $page['id'] === (string) ($node['reference_id'] ?? '') ? 'selected' : '' ?>><?= $this->e($page['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field" id="<?= $urlFieldId ?>" style="<?= $node['type'] === 'custom' ? '' : 'display:none;' ?>">
                                <label for="<?= $modalId ?>-url">URL</label>
                                <input type="text" id="<?= $modalId ?>-url" name="url" value="<?= $this->e((string) ($node['url'] ?? '')) ?>">
                            </div>
                            <div class="field">
                                <label for="<?= $modalId ?>-target">Mở tab</label>
                                <select id="<?= $modalId ?>-target" name="target">
                                    <option value="_self" <?= ($node['target'] ?? '_self') === '_self' ? 'selected' : '' ?>>Cùng tab</option>
                                    <option value="_blank" <?= ($node['target'] ?? '_self') === '_blank' ? 'selected' : '' ?>>Tab mới</option>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="btn btn-primary">Lưu</button>
                                <button type="button" class="btn btn-secondary" data-modal-close="<?= $modalId ?>">Đóng</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($node['children'])): ?>
                <ul class="menu-tree-children">
                    <?php foreach ($node['children'] as $child) {
                        $renderNode($child);
                    } ?>
                </ul>
                <?php endif; ?>
            </li>
            <?php
        };

foreach ($tree as $rootNode) {
    $renderNode($rootNode);
}
?>
    </ul>
    <?php if (empty($tree)): ?>
    <div class="empty-state">Chưa có menu item nào.</div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
