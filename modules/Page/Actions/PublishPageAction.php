<?php

declare(strict_types=1);

namespace Modules\Page\Actions;

use Core\Database;
use Core\TenantManager;
use Core\Validator;

/**
 * Pilot Action Class Pattern (Phase 6) - nghiep vu "doi trang thai Publish" dung chung giua
 * Modules\Page\PublishPageController (JSON) va Modules\Admin\PagePublishController (Admin HTML).
 * published_at chi set LAN DAU (COALESCE), khong ghi de khi publish lai.
 */
final class PublishPageAction
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws PageNotFoundException
     * @throws PageValidationException
     */
    public function execute(int $pageId, array $data): void
    {
        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $this->tenantManager->id()]
        );

        if ($page === null) {
            throw new PageNotFoundException();
        }

        $result = $this->validator->validate($data, [
            'status' => 'required|in:draft,published,scheduled',
        ]);

        if ($result->fails()) {
            throw new PageValidationException('Du lieu khong hop le.', $result->errors());
        }

        $this->database->statement(
            'UPDATE pages SET status = ?, published_at = COALESCE(published_at, CURRENT_TIMESTAMP) WHERE id = ?',
            [(string) $data['status'], $pageId]
        );
    }
}
