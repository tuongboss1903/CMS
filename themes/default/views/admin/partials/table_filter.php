<?php
/**
 * Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055). Form loc/tim kiem dung chung cho cac trang
 * danh sach (GET, khong CSRF - chi doc). Bien:
 *   $filter_action (string) - URL submit (vd "/admin/audit-logs")
 *   $filter_fields (list<array{
 *       name: string, label: string, value: string,
 *       type?: 'text'|'date'|'select', options?: list<array{value:string, label:string}>
 *   }>)
 * CHI render field Controller da THUC SU ho tro query param tuong ung - khong tu bia field
 * khong duoc xu ly phia Controller (se tao UI "loc" khong that su loc duoc gi).
 */
$fields = $filter_fields ?? [];

if (empty($fields)) {
    return;
}
?>
<form method="GET" action="<?= $this->e((string) ($filter_action ?? '')) ?>" class="table-filter">
<?php foreach ($fields as $field): ?>
    <div class="field">
        <label for="filter-<?= $this->e($field['name']) ?>"><?= $this->e($field['label']) ?></label>
        <?php if (($field['type'] ?? 'text') === 'select'): ?>
        <select id="filter-<?= $this->e($field['name']) ?>" name="<?= $this->e($field['name']) ?>">
            <option value="">-- Tất cả --</option>
            <?php foreach (($field['options'] ?? []) as $option): ?>
            <option value="<?= $this->e((string) $option['value']) ?>"<?= (string) $option['value'] === (string) ($field['value'] ?? '') ? ' selected' : '' ?>><?= $this->e((string) $option['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="<?= $this->e((string) ($field['type'] ?? 'text')) ?>" id="filter-<?= $this->e($field['name']) ?>" name="<?= $this->e($field['name']) ?>" value="<?= $this->e((string) ($field['value'] ?? '')) ?>">
        <?php endif; ?>
    </div>
<?php endforeach; ?>
    <div class="field">
        <button type="submit" class="btn btn-primary"><?php $this->include('admin.partials.icon', ['name' => 'filter']); ?> Lọc</button>
        <a href="<?= $this->e((string) ($filter_action ?? '')) ?>" class="btn btn-secondary">Xoá lọc</a>
    </div>
</form>
