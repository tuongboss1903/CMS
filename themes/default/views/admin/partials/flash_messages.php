<?php
/**
 * Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055). Chuan hoa hien thi Session::flash(). View
 * KHONG duoc phep tu doc Session (nguyen tac kien truc "View khong truy van Database/Session
 * truc tiep" - chi Controller moi lam) - partial nay CHI hien thi bien da duoc Controller doc san
 * qua Session::getFlash() va truyen vao render() qua 3 key: $flash_success/$flash_warning/
 * $flash_error. Neu Controller nao chua truyen (da dinh - HARD CONSTRAINT Phase 18 khong sua
 * Controller), partial nay khong render gi ca (an toan, khong loi bien chua dinh nghia).
 */
?>
<?php if (!empty($flash_success)): ?>
<div class="alert alert-success is-dismissible" data-flash><span><?= $this->e((string) $flash_success) ?></span><button type="button" class="alert-dismiss" data-flash-dismiss aria-label="Dong thong bao">&times;</button></div>
<?php endif; ?>
<?php if (!empty($flash_warning)): ?>
<div class="alert alert-warning is-dismissible" data-flash><span><?= $this->e((string) $flash_warning) ?></span><button type="button" class="alert-dismiss" data-flash-dismiss aria-label="Dong thong bao">&times;</button></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
<div class="alert alert-danger is-dismissible" data-flash><span><?= $this->e((string) $flash_error) ?></span><button type="button" class="alert-dismiss" data-flash-dismiss aria-label="Dong thong bao">&times;</button></div>
<?php endif; ?>
