<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/comments/bulk-delete - xoa nhieu Comment cung luc (checkbox chon dong, Design
 * Audit Phase 7). Hard delete (khong deleted_at, giong CommentDeleteController - day la log/UGC).
 */
final class CommentBulkDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('comment.delete')) {
            return Response::html('403 Forbidden', 403);
        }

        $siteId = $this->tenantManager->id();
        $ids = $this->sanitizeIds((array) $request->input('ids', []));

        if ($ids === []) {
            return Response::redirect('/admin/comments');
        }

        $idsPlaceholders = \implode(',', \array_fill(0, \count($ids), '?'));

        $ownedIds = $this->database->select(
            "SELECT id FROM comments WHERE tenant_id = ? AND id IN ({$idsPlaceholders})",
            [$siteId, ...$ids]
        );

        if ($ownedIds === []) {
            return Response::redirect('/admin/comments');
        }

        $ownedIdList = \array_map(static fn (array $row): int => (int) $row['id'], $ownedIds);
        $ownedPlaceholders = \implode(',', \array_fill(0, \count($ownedIdList), '?'));

        $this->database->statement("DELETE FROM comments WHERE id IN ({$ownedPlaceholders})", $ownedIdList);

        return Response::redirect('/admin/comments');
    }

    /**
     * @param array<mixed> $rawIds
     * @return list<int>
     */
    private function sanitizeIds(array $rawIds): array
    {
        $ids = \array_map(static fn (mixed $id): int => (int) $id, $rawIds);
        $ids = \array_filter($ids, static fn (int $id): bool => $id > 0);

        return \array_values(\array_unique($ids));
    }
}
