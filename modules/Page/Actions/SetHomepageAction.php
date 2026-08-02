<?php

declare(strict_types=1);

namespace Modules\Page\Actions;

use Core\Database;
use Core\TenantManager;

/**
 * Pilot Action Class Pattern (Phase 6) - nghiep vu "dat lam trang chu" dung chung giua
 * Modules\Page\SetHomepageController (JSON) va Modules\Admin\PageSetHomepageController (Admin HTML).
 * Database::transaction() bao boc dung 2 buoc UPDATE (bo trang chu cu, dat trang chu moi).
 */
final class SetHomepageAction
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    /** @throws PageNotFoundException */
    public function execute(int $pageId): void
    {
        $siteId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $siteId]
        );

        if ($page === null) {
            throw new PageNotFoundException();
        }

        $this->database->transaction(function (Database $db) use ($pageId, $siteId): void {
            $db->statement('UPDATE pages SET is_homepage = 0 WHERE tenant_id = ? AND is_homepage = 1', [$siteId]);
            $db->statement('UPDATE pages SET is_homepage = 1 WHERE id = ? AND tenant_id = ?', [$pageId, $siteId]);
        });
    }
}
