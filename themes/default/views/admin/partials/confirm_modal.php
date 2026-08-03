<?php
/**
 * Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055). Modal xac nhan DUNG CHUNG toan Admin - 1
 * instance duy nhat trong DOM (include tu admin.layouts.main), id co dinh "confirm-modal". JS
 * (public/assets/js/app.js) tu dong bat MOI form co attribute data-confirm="..." de mo modal nay
 * thay the window.confirm() - khong can sua tung view rieng le, chi can giu nguyen attribute
 * data-confirm da co san tren cac form (vd modules Media/Comment/AuditLog/SystemSettings).
 * Tai su dung nguyen .modal-overlay/.modal va co che [data-modal-close] da co san tu truoc
 * (Media Upload Modal, Phase 7) - khong tao co che modal moi.
 */
?>
<div class="modal-overlay" id="confirm-modal">
    <div class="modal">
        <h2>Xac nhan</h2>
        <p data-confirm-message class="text-muted mb-0"></p>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" data-modal-close="confirm-modal">Huy</button>
            <button type="button" class="btn btn-danger" data-confirm-accept>Xac nhan</button>
        </div>
    </div>
</div>
