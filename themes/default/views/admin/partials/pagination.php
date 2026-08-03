<?php
/**
 * Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055). Rut gon logic phan trang da viet tay o
 * AuditLogController's list.php (Phase 16) thanh partial dung chung. Bien bat buoc:
 *   $page (int)         - trang hien tai
 *   $total_pages (int)  - tong so trang
 *   $base_url (string)  - URL DA CHUA het query string con lai + dau "?" hoac "&" cuoi cung, vd
 *                          "/admin/audit-logs?event=page.created&" - partial tu noi them "page=N".
 * Khong render gi neu total_pages <= 1 (khong can phan trang).
 */
if ((int) ($total_pages ?? 1) <= 1) {
    return;
}
?>
<div class="pagination">
    <span class="pagination-summary">Trang <?= $this->e((string) $page) ?>/<?= $this->e((string) $total_pages) ?></span>
    <?php for ($p = 1; $p <= (int) $total_pages; $p++): ?>
    <?php if ($p === (int) $page): ?>
    <span class="pagination-current"><?= $this->e((string) $p) ?></span>
    <?php else: ?>
    <a href="<?= $this->e($base_url . 'page=' . $p) ?>"><?= $this->e((string) $p) ?></a>
    <?php endif; ?>
    <?php endfor; ?>
</div>
