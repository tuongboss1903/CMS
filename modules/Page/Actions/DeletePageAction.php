<?php

declare(strict_types=1);

namespace Modules\Page\Actions;

use Core\Database;
use Core\TenantManager;

/**
 * Pilot Action Class Pattern (Phase 6) - nghiep vu "xoa mem Page" dung chung giua
 * Modules\Page\DeletePageController (JSON) va Modules\Admin\PageDeleteController (Admin HTML).
 */
final class DeletePageAction
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    /** @throws PageNotFoundException */
    public function execute(int $pageId): void
    {
        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $this->tenantManager->id()]
        );

        if ($page === null) {
            throw new PageNotFoundException();
        }

        $this->database->statement('UPDATE pages SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?', [$pageId]);
    }
}
